<?php

namespace Modules\TitanCore\Support;

use Illuminate\Support\Facades\Log;

/**
 * Validates AI asset manifests (asset.json, provider.json, agent.json, tool.json,
 * prompt.json, workflow.json, engine.json) for completeness and structural correctness.
 *
 * Designed for graceful failure: every public method returns a
 * {@see ManifestValidationResult} and never throws — callers decide whether
 * to halt, warn, or skip based on the result status.
 *
 * Rules enforced:
 *  1. Required fields must be present and non-empty.
 *  2. `version` must be a non-empty string (semantic version recommended).
 *  3. `capabilities` must be a non-empty array when present.
 *  4. `schema_version` (optional) must be in SUPPORTED_SCHEMA_VERSIONS when declared.
 *  5. Item arrays (providers, tools, prompts, workflows, agents) must contain
 *     only array entries; each entry is validated for its own required subfields.
 *
 * Usage:
 *   $v = new AssetManifestValidator();
 *
 *   // Validate a file on disk:
 *   $result = $v->validateFile('/path/to/provider.json', 'provider');
 *
 *   // Validate already-decoded data:
 *   $result = $v->validateRequiredFields($data, ['name','version','description'], 'provider.json');
 *
 *   if (! $result->isValid()) {
 *       foreach ($result->errors() as $err) { ... }
 *   }
 */
class AssetManifestValidator
{
    /** Supported schema_version values */
    public const SUPPORTED_SCHEMA_VERSIONS = ['1.0.0'];

    /** Supported manifest_version values */
    public const SUPPORTED_MANIFEST_VERSIONS = ['1.0.0'];

    /**
     * Required fields for each manifest type's top-level object.
     * An empty array means "no extra requirements beyond name+version+description".
     */
    private const REQUIRED_BY_TYPE = [
        'asset'    => ['name', 'version', 'description', 'capabilities', 'module'],
        'provider' => ['name', 'version', 'description', 'providers', 'capabilities', 'module'],
        'agent'    => ['name', 'version', 'description', 'capabilities', 'module'],
        'tool'     => ['name', 'version', 'description', 'tools', 'capabilities', 'module'],
        'prompt'   => ['name', 'version', 'description', 'prompts', 'module'],
        'workflow' => ['name', 'version', 'description', 'workflows', 'capabilities', 'module'],
        'engine'   => ['name', 'version', 'description', 'engines', 'capabilities', 'module'],
    ];

    /** Required subfields on each item within a provider 'providers' array */
    private const PROVIDER_ITEM_REQUIRED = ['id', 'name', 'description', 'capabilities'];

    /** Required subfields on each item within a tool 'tools' array */
    private const TOOL_ITEM_REQUIRED = ['id', 'name', 'description', 'risk_class', 'parameters'];

    /** Required subfields on each item within a workflow 'workflows' array */
    private const WORKFLOW_ITEM_REQUIRED = ['id', 'name', 'description', 'steps'];

    /** Required subfields on each item within a prompt 'prompts' array */
    private const PROMPT_ITEM_REQUIRED = ['id', 'name', 'description', 'category', 'variables'];

    /**
     * Required subfields on each item within an engine 'engines' array.
     *
     * lifecycle is expected to be a non-empty status descriptor
     * (for example: managed, registered, installed, loaded, running).
     */
    private const ENGINE_ITEM_REQUIRED = ['id', 'name', 'description', 'class', 'version', 'lifecycle'];

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Validate a manifest file on disk.
     *
     * @param  string       $filePath  Absolute path to the JSON file.
     * @param  string|null  $type      Manifest type hint (asset|provider|agent|tool|prompt|workflow|engine).
     *                                 Auto-detects from filename when omitted.
     */
    public function validateFile(string $filePath, ?string $type = null): ManifestValidationResult
    {
        if (! file_exists($filePath)) {
            return ManifestValidationResult::failure(
                basename($filePath),
                ["Manifest file not found: {$filePath}"]
            );
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return ManifestValidationResult::failure(
                basename($filePath),
                ["Cannot read manifest file: {$filePath}"]
            );
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ManifestValidationResult::failure(
                basename($filePath),
                ["Invalid JSON in " . basename($filePath) . ": " . json_last_error_msg()]
            );
        }

        if (! is_array($data)) {
            return ManifestValidationResult::failure(
                basename($filePath),
                ["Manifest root must be a JSON object."]
            );
        }

        $resolvedType = $type ?? $this->detectType($filePath);

        return $this->validateData($data, $resolvedType, basename($filePath));
    }

    /**
     * Validate decoded manifest data against a given type.
     *
     * @param  array   $data
     * @param  string  $type   asset|provider|agent|tool|prompt|workflow|engine
     * @param  string  $label  Label for error messages (e.g. filename).
     */
    public function validateData(array $data, string $type, string $label = 'manifest'): ManifestValidationResult
    {
        $errors   = [];
        $warnings = [];

        // 1. Schema / manifest version check (if declared)
        $manifestVersionKey = array_key_exists('manifest_version', $data)
            ? 'manifest_version'
            : (array_key_exists('schema_version', $data) ? 'schema_version' : null);
        $manifestVersion = $manifestVersionKey !== null ? $data[$manifestVersionKey] : null;
        if (is_string($manifestVersion) && $manifestVersion !== '') {
            $supportedVersions = array_values(array_unique(array_merge(self::SUPPORTED_SCHEMA_VERSIONS, self::SUPPORTED_MANIFEST_VERSIONS)));

            if (! in_array($manifestVersion, $supportedVersions, true)) {
                $errors[] = sprintf(
                    'Unknown %s "%s" in %s. Supported: [%s].',
                    $manifestVersionKey,
                    $manifestVersion,
                    $label,
                    implode(', ', $supportedVersions)
                );
            }
        }

        // 2. Required top-level fields for this type
        $required = self::REQUIRED_BY_TYPE[$type] ?? ['name', 'version', 'description'];
        foreach ($required as $field) {
            if ($field === 'capabilities' && array_key_exists($field, $data) && is_array($data[$field])) {
                continue;
            }

            if (! isset($data[$field]) || $data[$field] === '' || $data[$field] === [] || $data[$field] === null) {
                $errors[] = "Missing or empty required field \"{$field}\" in {$label}.";
            }
        }

        if (! empty($errors)) {
            // Stop early — item-level validation is pointless if top-level is broken
            return ManifestValidationResult::failure($label, $errors);
        }

        // 3. Version must be a non-empty string
        if (! is_string($data['version']) || trim($data['version']) === '') {
            $errors[] = "Field \"version\" must be a non-empty string in {$label}.";
        }

        // 4. capabilities must be a non-empty list when present at root
        if (isset($data['capabilities'])) {
            if (! is_array($data['capabilities'])) {
                $errors[] = "Field \"capabilities\" must be an array in {$label}.";
            } elseif (empty($data['capabilities'])) {
                $warnings[] = "Field \"capabilities\" is present but empty in {$label}. Consider declaring at least one capability.";
            }
        }

        // 5. discovery_metadata warnings
        if (! isset($data['discovery_metadata'])) {
            $warnings[] = "Field \"discovery_metadata\" is missing in {$label}. Platform Manager auto-registration may not work.";
        }

        // 6. Item-level validation by type
        $itemErrors = match ($type) {
            'provider' => $this->validateItems($data['providers'] ?? [], self::PROVIDER_ITEM_REQUIRED, 'providers', $label),
            'tool'     => $this->validateItems($data['tools'] ?? [], self::TOOL_ITEM_REQUIRED, 'tools', $label),
            'workflow' => $this->validateItems($data['workflows'] ?? [], self::WORKFLOW_ITEM_REQUIRED, 'workflows', $label),
            'prompt'   => $this->validateItems($data['prompts'] ?? [], self::PROMPT_ITEM_REQUIRED, 'prompts', $label),
            'engine'   => $this->validateItems($data['engines'] ?? [], self::ENGINE_ITEM_REQUIRED, 'engines', $label),
            default    => [],
        };

        $errors = array_merge($errors, $itemErrors);

        if (! empty($errors)) {
            return ManifestValidationResult::failure($label, $errors);
        }

        if (! empty($warnings)) {
            return ManifestValidationResult::warning($label, $warnings);
        }

        return ManifestValidationResult::success($label);
    }

    /**
     * Validate that all required fields are present and non-empty.
     * A lightweight check used by AssetDiscoveryService before full validation.
     *
     * @param  array    $data
     * @param  string[] $requiredFields
     * @param  string   $label
     */
    public function validateRequiredFields(array $data, array $requiredFields, string $label = 'manifest'): ManifestValidationResult
    {
        $errors = [];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data)) {
                $errors[] = "Missing required field \"{$field}\" in {$label}.";
                continue;
            }

            $value = $data[$field];
            if ($value === null || $value === '' || $value === []) {
                $errors[] = "Required field \"{$field}\" is present but empty in {$label}.";
            }
        }

        if (! empty($errors)) {
            return ManifestValidationResult::failure($label, $errors);
        }

        return ManifestValidationResult::success($label);
    }

    /**
     * Validate multiple manifest files and return all results.
     *
     * @param  array<string, string>  $files  Map of type => absolute file path.
     * @return ManifestValidationResult[]      Keyed by the same type strings.
     */
    public function validateAll(array $files): array
    {
        $results = [];

        foreach ($files as $type => $path) {
            $results[$type] = $this->validateFile($path, $type);
        }

        return $results;
    }

    /**
     * Return true if all results in an array are valid.
     *
     * @param  ManifestValidationResult[]  $results
     */
    public function allValid(array $results): bool
    {
        foreach ($results as $result) {
            if (! $result->isValid()) {
                return false;
            }
        }

        return true;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Detect manifest type from filename.
     */
    private function detectType(string $filePath): string
    {
        $basename = strtolower(basename($filePath));

        return match (true) {
            str_starts_with($basename, 'asset')    => 'asset',
            str_starts_with($basename, 'provider') => 'provider',
            str_starts_with($basename, 'agent')    => 'agent',
            str_starts_with($basename, 'tool')     => 'tool',
            str_starts_with($basename, 'prompt')   => 'prompt',
            str_starts_with($basename, 'workflow')  => 'workflow',
            str_starts_with($basename, 'engine')   => 'engine',
            default                                => 'asset',
        };
    }

    /**
     * Validate item-level arrays in registry manifests.
     *
     * @param  mixed    $items
     * @param  string[] $requiredSubfields
     * @param  string   $key               Array key name for error context ('providers', 'tools', etc.)
     * @param  string   $label             Parent manifest label.
     * @return string[]  Error messages.
     */
    private function validateItems(mixed $items, array $requiredSubfields, string $key, string $label): array
    {
        $errors = [];

        if (! is_array($items)) {
            return ["Field \"{$key}\" must be an array in {$label}."];
        }

        if (empty($items)) {
            return ["Field \"{$key}\" is empty in {$label}. At least one item is required."];
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $errors[] = "Item at {$key}[{$index}] must be an object in {$label}.";
                continue;
            }

            foreach ($requiredSubfields as $field) {
                if (! isset($item[$field]) || $item[$field] === '' || $item[$field] === null) {
                    $errors[] = "Missing required field \"{$field}\" in {$key}[{$index}] of {$label}.";
                }
            }
        }

        return $errors;
    }
}
