<?php

namespace Modules\TitanCore\Support;

/**
 * Validates Titan module manifests against JSON Schema definitions.
 *
 * Schemas live in resources/schemas/titan/ and are keyed by manifest type.
 * Manifests may declare a `schema_version` field; the validator uses the
 * matching schema version.  If an unknown version is declared, the manifest
 * is rejected regardless of the validation mode.
 *
 * Two modes are supported:
 *   strict  — any invalid manifest throws a ManifestValidationException.
 *   warning — invalid manifests are reported but do not halt processing.
 *
 * Usage:
 *   $validator = new ManifestSchemaValidator();
 *   $result    = $validator->validateFile('/path/to/module.json', 'module.json');
 *   if (! $result->isValid()) {
 *       foreach ($result->errors() as $err) { ... }
 *   }
 */
class ManifestSchemaValidator
{
    /** @var array<string,string> Map of manifest filename pattern => schema file name */
    private const SCHEMA_MAP = [
        'module.json'          => 'module.json.schema.json',
        'module.manifest.json' => 'module.manifest.schema.json',
        'blueprint.manifest'   => 'blueprint.manifest.schema.json',
        'ai.manifest'          => 'ai.manifest.schema.json',
        'billing.manifest'     => 'billing.manifest.schema.json',
        'workflow.manifest'    => 'workflow.manifest.schema.json',
        'workflows.manifest'   => 'workflow.manifest.schema.json',
    ];

    /** Current supported schema version */
    public const CURRENT_SCHEMA_VERSION = '1.0.0';

    /** @var string[] All supported schema versions */
    public const SUPPORTED_SCHEMA_VERSIONS = ['1.0.0'];

    /** @var string Path to the schemas directory */
    private string $schemasPath;

    public function __construct(?string $schemasPath = null)
    {
        $this->schemasPath = $schemasPath ?? resource_path('schemas/titan');
    }

    /**
     * Validate a manifest file path against the appropriate schema.
     *
     * @param  string  $manifestPath  Absolute path to the manifest file.
     * @param  string|null  $typeHint  Manifest type hint (e.g. 'module.json'). Auto-detected if omitted.
     */
    public function validateFile(string $manifestPath, ?string $typeHint = null): ManifestValidationResult
    {
        if (! file_exists($manifestPath)) {
            return ManifestValidationResult::failure(
                basename($manifestPath),
                [sprintf('Manifest file not found: %s', $manifestPath)]
            );
        }

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            return ManifestValidationResult::failure(
                basename($manifestPath),
                [sprintf('Cannot read manifest file: %s', $manifestPath)]
            );
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ManifestValidationResult::failure(
                basename($manifestPath),
                [sprintf('Invalid JSON in %s: %s', basename($manifestPath), json_last_error_msg())]
            );
        }

        $type = $typeHint ?? $this->detectType($manifestPath);

        return $this->validateData($data, $type, basename($manifestPath));
    }

    /**
     * Validate already-decoded manifest data.
     *
     * @param  array<string,mixed>  $data
     */
    public function validateData(array $data, string $type, string $label = 'manifest'): ManifestValidationResult
    {
        // 1. Schema version check
        if (isset($data['schema_version'])) {
            $declaredVersion = (string) $data['schema_version'];
            if (! in_array($declaredVersion, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
                return ManifestValidationResult::failure($label, [
                    sprintf(
                        'Unknown schema_version "%s" in %s. Supported: [%s]',
                        $declaredVersion,
                        $label,
                        implode(', ', self::SUPPORTED_SCHEMA_VERSIONS)
                    ),
                ]);
            }
        }

        // 2. Load schema
        $schemaFile = $this->resolveSchemaFile($type);
        if ($schemaFile === null) {
            // No schema registered for this type — skip with a warning
            return ManifestValidationResult::warning($label, [
                sprintf('No schema registered for manifest type "%s". Skipping schema validation.', $type),
            ]);
        }

        if (! file_exists($schemaFile)) {
            return ManifestValidationResult::warning($label, [
                sprintf('Schema file not found: %s', $schemaFile),
            ]);
        }

        $schema = json_decode(file_get_contents($schemaFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ManifestValidationResult::warning($label, [
                sprintf('Schema file contains invalid JSON: %s', $schemaFile),
            ]);
        }

        // 3. Validate data against schema
        $errors = $this->validateAgainstSchema($data, $schema, '');

        if (! empty($errors)) {
            return ManifestValidationResult::failure($label, $errors);
        }

        return ManifestValidationResult::success($label);
    }

    /**
     * Validate all manifests for a module directory.
     *
     * @param  string  $moduleDir  Absolute path to the module root.
     * @return ManifestValidationResult[]
     */
    public function validateModule(string $moduleDir): array
    {
        $results = [];

        // Validate module.json
        $moduleJson = $moduleDir . '/module.json';
        if (file_exists($moduleJson)) {
            $results[] = $this->validateFile($moduleJson, 'module.json');
        }

        // Validate module.manifest.json
        $moduleManifest = $moduleDir . '/module.manifest.json';
        if (file_exists($moduleManifest)) {
            $results[] = $this->validateFile($moduleManifest, 'module.manifest.json');
        }

        // Validate manifests/*.manifest.json
        $manifestsDir = $moduleDir . '/manifests';
        if (is_dir($manifestsDir)) {
            foreach (glob($manifestsDir . '/*.manifest.json') ?: [] as $file) {
                $results[] = $this->validateFile($file);
            }
        }

        return $results;
    }

    /**
     * Detect the manifest type from the file path.
     */
    public function detectType(string $filePath): string
    {
        $filename = strtolower(basename($filePath));

        if ($filename === 'module.json') {
            return 'module.json';
        }

        if ($filename === 'module.manifest.json') {
            return 'module.manifest.json';
        }

        // Strip .json suffix, then check for known manifest suffixes
        $base = preg_replace('/\.json$/', '', $filename) ?? $filename;

        foreach (array_keys(self::SCHEMA_MAP) as $type) {
            if ($base === $type || str_ends_with($base, '.' . $type)) {
                return $type;
            }
        }

        // Fall back to the last two dot-segments (e.g. "ai.manifest")
        $parts = explode('.', $base);
        if (count($parts) >= 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $base;
    }

    /**
     * Resolve the absolute path to the schema file for a given manifest type.
     */
    private function resolveSchemaFile(string $type): ?string
    {
        // Exact match
        if (isset(self::SCHEMA_MAP[$type])) {
            return $this->schemasPath . '/' . self::SCHEMA_MAP[$type];
        }

        // Suffix match (e.g. "ai.manifest" matches "ai.manifest.schema.json")
        foreach (self::SCHEMA_MAP as $pattern => $schemaFile) {
            if (str_ends_with($type, $pattern)) {
                return $this->schemasPath . '/' . $schemaFile;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JSON Schema validation (draft-07 subset)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate $data against a $schema, returning a list of error strings.
     *
     * @param  mixed  $data
     * @param  array<string,mixed>  $schema
     * @param  string  $path  JSON pointer path for error messages.
     * @return string[]
     */
    private function validateAgainstSchema(mixed $data, array $schema, string $path): array
    {
        $errors = [];

        // type
        if (isset($schema['type'])) {
            $typeErrors = $this->validateType($data, $schema['type'], $path);
            if (! empty($typeErrors)) {
                return $typeErrors; // type mismatch; further checks are meaningless
            }
        }

        // enum
        if (isset($schema['enum'])) {
            if (! in_array($data, $schema['enum'], true)) {
                $errors[] = $this->pathErr($path, sprintf(
                    'Value must be one of [%s], got "%s".',
                    implode(', ', array_map(fn ($v) => json_encode($v), $schema['enum'])),
                    json_encode($data)
                ));
            }
        }

        // String-specific constraints
        if (is_string($data)) {
            if (isset($schema['minLength']) && mb_strlen($data) < $schema['minLength']) {
                $errors[] = $this->pathErr($path, sprintf(
                    'String too short (min %d characters, got %d).',
                    $schema['minLength'],
                    mb_strlen($data)
                ));
            }
            if (isset($schema['maxLength']) && mb_strlen($data) > $schema['maxLength']) {
                $errors[] = $this->pathErr($path, sprintf(
                    'String too long (max %d characters, got %d).',
                    $schema['maxLength'],
                    mb_strlen($data)
                ));
            }
            if (isset($schema['pattern']) && ! preg_match('/' . $schema['pattern'] . '/', $data)) {
                $errors[] = $this->pathErr($path, sprintf(
                    'Value "%s" does not match pattern "%s".',
                    $data,
                    $schema['pattern']
                ));
            }
        }

        // Number-specific constraints
        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = $this->pathErr($path, sprintf(
                    'Value %s is less than minimum %s.',
                    $data,
                    $schema['minimum']
                ));
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = $this->pathErr($path, sprintf(
                    'Value %s is greater than maximum %s.',
                    $data,
                    $schema['maximum']
                ));
            }
        }

        // Array-specific constraints
        if (is_array($data) && array_is_list($data)) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = $this->pathErr($path, sprintf(
                    'Array has too few items (min %d, got %d).',
                    $schema['minItems'],
                    count($data)
                ));
            }
            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = $this->pathErr($path, sprintf(
                    'Array has too many items (max %d, got %d).',
                    $schema['maxItems'],
                    count($data)
                ));
            }
            if (isset($schema['items'])) {
                foreach ($data as $i => $item) {
                    $itemErrors = $this->validateAgainstSchema($item, $schema['items'], $path . '[' . $i . ']');
                    $errors = array_merge($errors, $itemErrors);
                }
            }
        }

        // Object-specific constraints
        if (is_array($data) && ! array_is_list($data)) {
            // required
            if (isset($schema['required'])) {
                foreach ($schema['required'] as $reqKey) {
                    if (! array_key_exists($reqKey, $data)) {
                        $errors[] = $this->pathErr($path, sprintf('Required field "%s" is missing.', $reqKey));
                    }
                }
            }

            // properties
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $propName => $propSchema) {
                    if (array_key_exists($propName, $data)) {
                        $propPath   = $path === '' ? $propName : $path . '.' . $propName;
                        $propErrors = $this->validateAgainstSchema($data[$propName], $propSchema, $propPath);
                        $errors     = array_merge($errors, $propErrors);
                    }
                }
            }

            // additionalProperties (false = no extra keys allowed)
            if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                $allowed  = array_keys($schema['properties'] ?? []);
                $extra    = array_diff(array_keys($data), $allowed);
                foreach ($extra as $extraKey) {
                    $errors[] = $this->pathErr($path, sprintf('Additional property "%s" is not allowed.', $extraKey));
                }
            }
        }

        return $errors;
    }

    /**
     * Validate that $data matches the expected type(s).
     *
     * @param  string|string[]  $type
     * @return string[]
     */
    private function validateType(mixed $data, string|array $type, string $path): array
    {
        $types = (array) $type;

        foreach ($types as $t) {
            if ($this->matchesType($data, $t)) {
                return [];
            }
        }

        $actual = $this->jsonType($data);

        return [$this->pathErr($path, sprintf(
            'Expected type %s, got %s.',
            implode('|', $types),
            $actual
        ))];
    }

    private function matchesType(mixed $data, string $type): bool
    {
        return match ($type) {
            'string'  => is_string($data),
            'integer' => is_int($data),
            'number'  => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'null'    => is_null($data),
            'array'   => is_array($data) && array_is_list($data),
            'object'  => is_array($data) && ! array_is_list($data),
            default   => false,
        };
    }

    private function jsonType(mixed $data): string
    {
        if (is_null($data)) {
            return 'null';
        }
        if (is_bool($data)) {
            return 'boolean';
        }
        if (is_int($data)) {
            return 'integer';
        }
        if (is_float($data)) {
            return 'number';
        }
        if (is_string($data)) {
            return 'string';
        }
        if (is_array($data)) {
            return array_is_list($data) ? 'array' : 'object';
        }

        return gettype($data);
    }

    private function pathErr(string $path, string $message): string
    {
        return $path !== '' ? "[{$path}] {$message}" : $message;
    }
}
