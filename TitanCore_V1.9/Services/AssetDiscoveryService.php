<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\Log;
use Modules\TitanCore\Support\AssetManifestValidator;

/**
 * Discovers TitanCore AI assets exclusively from their JSON metadata files.
 *
 * Scanning order for a module directory:
 *   1. AI/asset.json          — master AI subsystem manifest (lists exported assets)
 *   2. AI/Providers/provider.json
 *   3. AI/Agents/agent.json
 *   4. AI/Tools/tool.json
 *   5. AI/Prompts/prompt.json
 *   6. AI/Workflows/workflow.json
 *
 * Every discovered manifest is fully validated with {@see AssetManifestValidator}
 * (schema version + required fields + item-level checks) before being returned.
 * Manifests that fail validation are skipped with a log warning; they never throw
 * so the rest of the platform continues to boot.
 *
 * All registry manifests use an `<type>s` array (e.g. `agents`, `providers`,
 * `tools`, `prompts`, `workflows`) as the container for individual items.
 *
 * Return shape of {@see discoverFromDirectory()}:
 * <pre>
 * [
 *   'asset'               => array|null,  // parsed asset.json
 *   'providers'           => array[],     // provider items
 *   'agents'              => array[],     // agent items
 *   'tools'               => array[],     // tool items
 *   'prompts'             => array[],     // prompt items
 *   'workflows'           => array[],     // workflow items
 *   'registry_capabilities' => string[], // root-level caps from each registry manifest
 *   'errors'              => string[],
 * ]
 * </pre>
 */
class AssetDiscoveryService
{
    /** Map of manifest type => relative path within AI/ */
    private const MANIFEST_FILES = [
        'asset'    => 'asset.json',
        'provider' => 'Providers/provider.json',
        'agent'    => 'Agents/agent.json',
        'tool'     => 'Tools/tool.json',
        'prompt'   => 'Prompts/prompt.json',
        'workflow' => 'Workflows/workflow.json',
    ];

    /** Map of manifest type => item-container key within the manifest JSON */
    private const ITEMS_KEY = [
        'provider' => 'providers',
        'agent'    => 'agents',
        'tool'     => 'tools',
        'prompt'   => 'prompts',
        'workflow' => 'workflows',
    ];

    public function __construct(
        private readonly AssetManifestValidator $validator = new AssetManifestValidator(),
    ) {}

    /**
     * Discover all assets from a module root directory.
     *
     * @param  string  $moduleDir  Absolute path to the module root (e.g. /path/to/Modules/TitanCore).
     */
    public function discoverAll(string $moduleDir): array
    {
        return $this->discoverFromDirectory($moduleDir . '/AI');
    }

    /**
     * Discover all assets from an AI/ subdirectory.
     *
     * @param  string  $aiDir  Absolute path to the AI/ directory.
     * @return array{
     *     asset: array|null,
     *     providers: array,
     *     agents: array,
     *     tools: array,
     *     prompts: array,
     *     workflows: array,
     *     registry_capabilities: string[],
     *     errors: string[],
     * }
     */
    public function discoverFromDirectory(string $aiDir): array
    {
        $result = [
            'asset'                  => null,
            'providers'              => [],
            'agents'                 => [],
            'tools'                  => [],
            'prompts'                => [],
            'workflows'              => [],
            'registry_capabilities'  => [],   // root-level caps from each registry manifest
            'errors'                 => [],
        ];

        if (! is_dir($aiDir)) {
            $result['errors'][] = "AI directory not found: {$aiDir}";
            Log::warning('[TitanCore][AssetDiscovery] AI directory not found', ['dir' => $aiDir]);

            return $result;
        }

        foreach (self::MANIFEST_FILES as $type => $relativePath) {
            $manifestPath = $aiDir . '/' . $relativePath;

            if (! file_exists($manifestPath)) {
                continue;
            }

            $parsed = $this->parseAndValidate($manifestPath, $type, $result['errors']);
            if ($parsed === null) {
                continue;
            }

            if ($type === 'asset') {
                $result['asset'] = $parsed;
            } else {
                $itemsKey = self::ITEMS_KEY[$type];

                // Store the extracted item array
                $result[$itemsKey] = $this->extractItems($parsed, $itemsKey, $type);

                // Collect root-level capabilities from this registry manifest so
                // indexCapabilities() can include them without re-reading the file.
                foreach ($parsed['capabilities'] ?? [] as $cap) {
                    if (is_string($cap) && $cap !== '') {
                        $result['registry_capabilities'][] = $cap;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Discover only provider metadata.
     *
     * @param  string  $aiDir  Absolute path to the AI/ directory.
     * @return array[]
     */
    public function discoverProviders(string $aiDir): array
    {
        return $this->discoverItemsFromManifest($aiDir, 'provider');
    }

    /**
     * Discover only tool metadata.
     *
     * @param  string  $aiDir  Absolute path to the AI/ directory.
     * @return array[]
     */
    public function discoverTools(string $aiDir): array
    {
        return $this->discoverItemsFromManifest($aiDir, 'tool');
    }

    /**
     * Discover only workflow metadata.
     *
     * @param  string  $aiDir  Absolute path to the AI/ directory.
     * @return array[]
     */
    public function discoverWorkflows(string $aiDir): array
    {
        return $this->discoverItemsFromManifest($aiDir, 'workflow');
    }

    /**
     * Discover only agent metadata.
     *
     * @param  string  $aiDir  Absolute path to the AI/ directory.
     * @return array[]
     */
    public function discoverAgents(string $aiDir): array
    {
        return $this->discoverItemsFromManifest($aiDir, 'agent');
    }

    /**
     * Discover only prompt metadata.
     *
     * @param  string  $aiDir  Absolute path to the AI/ directory.
     * @return array[]
     */
    public function discoverPrompts(string $aiDir): array
    {
        return $this->discoverItemsFromManifest($aiDir, 'prompt');
    }

    /**
     * Return all unique capability strings declared across all discovered manifests.
     *
     * Aggregates from:
     *  - asset.json root 'capabilities'
     *  - Registry-level root 'capabilities' from provider/agent/tool/prompt/workflow manifests
     *    (stored in `registry_capabilities` by discoverFromDirectory)
     *  - Per-item 'capabilities' from every provider, agent, tool, workflow, prompt item
     *
     * @param  array  $discovered  Output of {@see discoverAll()} or {@see discoverFromDirectory()}.
     * @return string[]  Deduplicated, re-indexed.
     */
    public function indexCapabilities(array $discovered): array
    {
        $capabilities = [];

        // 1. Top-level asset.json capabilities
        foreach ($discovered['asset']['capabilities'] ?? [] as $cap) {
            $capabilities[] = $cap;
        }

        // 2. Registry-level root capabilities collected from all registry manifests
        foreach ($discovered['registry_capabilities'] ?? [] as $cap) {
            $capabilities[] = $cap;
        }

        // 3. Per-item capabilities (providers, agents, tools, workflows, prompts)
        foreach (['providers', 'agents', 'tools', 'workflows', 'prompts'] as $group) {
            foreach ($discovered[$group] ?? [] as $item) {
                foreach ($item['capabilities'] ?? [] as $cap) {
                    $capabilities[] = $cap;
                }
            }
        }

        // Deduplicate and re-index
        return array_values(array_unique(array_filter($capabilities, fn ($c) => is_string($c) && $c !== '')));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Parse, fully validate, and return items from a single manifest type.
     *
     * @param  string  $aiDir
     * @param  string  $type  provider|agent|tool|prompt|workflow
     * @return array[]
     */
    private function discoverItemsFromManifest(string $aiDir, string $type): array
    {
        $manifestPath = $aiDir . '/' . self::MANIFEST_FILES[$type];

        if (! file_exists($manifestPath)) {
            return [];
        }

        $errors = [];
        $parsed = $this->parseAndValidate($manifestPath, $type, $errors);

        if ($parsed === null) {
            foreach ($errors as $err) {
                Log::warning('[TitanCore][AssetDiscovery] Discovery error', [
                    'type'  => $type,
                    'error' => $err,
                ]);
            }

            return [];
        }

        return $this->extractItems($parsed, self::ITEMS_KEY[$type], $type);
    }

    /**
     * Parse a JSON manifest file and run full validation via
     * {@see AssetManifestValidator::validateData()} (schema version + required
     * fields + item-level checks). Returns null on any failure, appending to $errors.
     *
     * @param  string    $manifestPath
     * @param  string    $type
     * @param  string[]  &$errors
     * @return array|null
     */
    private function parseAndValidate(string $manifestPath, string $type, array &$errors): ?array
    {
        $raw = @file_get_contents($manifestPath);
        if ($raw === false) {
            $err      = "Cannot read manifest file: {$manifestPath}";
            $errors[] = $err;
            Log::warning('[TitanCore][AssetDiscovery] ' . $err);

            return null;
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $err      = "Invalid JSON in {$manifestPath}: " . json_last_error_msg();
            $errors[] = $err;
            Log::warning('[TitanCore][AssetDiscovery] ' . $err);

            return null;
        }

        if (! is_array($data)) {
            $err      = "Manifest root must be a JSON object: {$manifestPath}";
            $errors[] = $err;
            Log::warning('[TitanCore][AssetDiscovery] ' . $err);

            return null;
        }

        // Full validation: schema version + required fields + item-level subfields
        $result = $this->validator->validateData($data, $type, basename($manifestPath));

        if (! $result->isValid()) {
            foreach ($result->errors() as $err) {
                $errors[] = $err;
                Log::warning('[TitanCore][AssetDiscovery] Manifest validation failed', [
                    'file'  => $manifestPath,
                    'error' => $err,
                ]);
            }

            return null;
        }

        // Warnings are non-fatal — log them but continue
        foreach ($result->warnings() as $warn) {
            Log::info('[TitanCore][AssetDiscovery] Manifest validation warning', [
                'file'    => $manifestPath,
                'warning' => $warn,
            ]);
        }

        return $data;
    }

    /**
     * Extract the item-level array from a parsed registry manifest.
     * Non-array entries are silently filtered out.
     *
     * @param  array   $manifest
     * @param  string  $key   e.g. 'providers', 'agents'
     * @param  string  $type  manifest type for logging
     * @return array[]
     */
    private function extractItems(array $manifest, string $key, string $type): array
    {
        $items = $manifest[$key] ?? [];

        if (! is_array($items)) {
            Log::warning('[TitanCore][AssetDiscovery] Expected array for manifest key', [
                'key'  => $key,
                'type' => $type,
            ]);

            return [];
        }

        return array_values(array_filter($items, fn ($item) => is_array($item)));
    }
}
