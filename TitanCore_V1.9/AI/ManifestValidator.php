<?php

namespace Modules\TitanCore\AI;

use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Validates module ai_tools.json manifests at boot and on demand.
 *
 * Each tool entry in ai_tools.json is checked for:
 *  - Required fields: description, risk_class, and either parameters or input_schema
 *  - If a `class` field is declared: the class must exist (class_exists())
 *  - If the class exists and has an execute() method: the return type must be array
 *
 * Usage:
 *   $validator = new ManifestValidator();
 *   $results   = $validator->validateAll();           // all modules
 *   $results   = $validator->validateModule('CRMCore'); // one module
 *
 * Each result is a ManifestValidationIssue with:
 *   ->module()   string  module name
 *   ->tool()     string  tool id/name within the manifest
 *   ->level()    string  'error' | 'warning'
 *   ->message()  string  human-readable description
 */
class ManifestValidator
{
    /** @var string[] Fields required on every tool entry */
    private const REQUIRED_FIELDS = ['description', 'risk_class'];

    /** @var string[] At least one of these fields must be present as a parameters schema */
    private const PARAM_FIELDS = ['parameters', 'input_schema'];

    /** @var string[] Valid risk_class values */
    private const VALID_RISK_CLASSES = ['read', 'advisory', 'write', 'destructive', 'admin'];

    /** @var string[] Supported manifest format versions */
    private const SUPPORTED_MANIFEST_VERSIONS = ['1.0.0'];

    private string $modulesBase;

    public function __construct(?string $modulesBase = null)
    {
        $this->modulesBase = $modulesBase ?? base_path(config('titan-modules.path', 'Modules'));
    }

    /**
     * Validate all module ai_tools.json manifests.
     *
     * @return ManifestValidationIssue[]
     */
    public function validateAll(): array
    {
        if (! is_dir($this->modulesBase)) {
            return [];
        }

        $issues = [];

        foreach (glob($this->modulesBase.'/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $issues = array_merge($issues, $this->validateModuleDir($moduleDir));
        }

        return $issues;
    }

    /**
     * Validate a single module by name.
     *
     * @return ManifestValidationIssue[]
     */
    public function validateModule(string $moduleName): array
    {
        $moduleDir = $this->modulesBase.DIRECTORY_SEPARATOR.$moduleName;

        if (! is_dir($moduleDir)) {
            return [
                ManifestValidationIssue::error($moduleName, 'module', "Module directory not found: {$moduleDir}"),
            ];
        }

        return $this->validateModuleDir($moduleDir);
    }

    /**
     * Validate the ai_tools.json for a module directory.
     *
     * @return ManifestValidationIssue[]
     */
    public function validateModuleDir(string $moduleDir): array
    {
        $moduleName  = basename($moduleDir);
        $manifestPath = $moduleDir.'/manifests/ai_tools.json';

        if (! is_file($manifestPath)) {
            return [];
        }

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            return [ManifestValidationIssue::error($moduleName, 'ai_tools.json', 'Could not read manifest file.')];
        }

        $manifest = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [ManifestValidationIssue::error($moduleName, 'ai_tools.json', 'Invalid JSON: '.json_last_error_msg())];
        }

        if (! is_array($manifest)) {
            return [ManifestValidationIssue::error($moduleName, 'ai_tools.json', 'Manifest root must be a JSON object.')];
        }

        $manifestVersion = $manifest['manifest_version'] ?? $manifest['schema_version'] ?? null;
        if (is_string($manifestVersion) && $manifestVersion !== '' && ! in_array($manifestVersion, self::SUPPORTED_MANIFEST_VERSIONS, true)) {
            return [
                ManifestValidationIssue::error(
                    $moduleName,
                    'ai_tools.json',
                    'Unsupported manifest_version "' . $manifestVersion . '". Supported versions: ' . implode(', ', self::SUPPORTED_MANIFEST_VERSIONS) . '.'
                ),
            ];
        }

        $tools = $manifest['tools'] ?? null;
        if ($tools === null) {
            return [];
        }

        if (! is_array($tools)) {
            return [ManifestValidationIssue::error($moduleName, 'ai_tools.json', '"tools" must be an array.')];
        }

        $issues = [];

        foreach ($tools as $index => $tool) {
            if (! is_array($tool)) {
                $issues[] = ManifestValidationIssue::error($moduleName, "tools[{$index}]", 'Tool entry must be a JSON object.');
                continue;
            }

            $toolId = $tool['id'] ?? $tool['name'] ?? (string) $index;
            $issues = array_merge($issues, $this->validateTool($moduleName, $toolId, $tool));
        }

        return $issues;
    }

    /**
     * Validate a single tool entry.
     *
     * @param  array<string, mixed>  $tool
     * @return ManifestValidationIssue[]
     */
    private function validateTool(string $moduleName, string $toolId, array $tool): array
    {
        $issues = [];

        // ── Required fields ───────────────────────────────────────────────────
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($tool[$field])) {
                $issues[] = ManifestValidationIssue::error(
                    $moduleName,
                    $toolId,
                    "Missing required field: \"{$field}\"."
                );
            }
        }

        // ── Parameters schema ─────────────────────────────────────────────────
        $hasParams = false;
        foreach (self::PARAM_FIELDS as $field) {
            if (isset($tool[$field]) && is_array($tool[$field])) {
                $hasParams = true;
                break;
            }
        }
        if (! $hasParams) {
            $issues[] = ManifestValidationIssue::error(
                $moduleName,
                $toolId,
                'Missing parameters schema. At least one of "parameters" or "input_schema" must be a non-empty object.'
            );
        }

        // ── risk_class value ──────────────────────────────────────────────────
        if (! empty($tool['risk_class']) && ! in_array($tool['risk_class'], self::VALID_RISK_CLASSES, true)) {
            $issues[] = ManifestValidationIssue::warning(
                $moduleName,
                $toolId,
                'Unknown risk_class "'.($tool['risk_class'] ?? '').'".'
                .' Expected one of: '.implode(', ', self::VALID_RISK_CLASSES).'.'
            );
        }

        // ── Class existence + execute() return type ───────────────────────────
        $class = $tool['class'] ?? $tool['handler'] ?? null;
        if (is_string($class) && $class !== '') {
            if (! class_exists($class)) {
                $issues[] = ManifestValidationIssue::error(
                    $moduleName,
                    $toolId,
                    "Declared class \"{$class}\" does not exist."
                );
            } else {
                $returnTypeIssue = $this->validateExecuteReturnType($moduleName, $toolId, $class);
                if ($returnTypeIssue !== null) {
                    $issues[] = $returnTypeIssue;
                }
            }
        }

        return $issues;
    }

    /**
     * Verify that the execute() method on a tool class declares array as its return type.
     */
    private function validateExecuteReturnType(string $moduleName, string $toolId, string $class): ?ManifestValidationIssue
    {
        if (! method_exists($class, 'execute')) {
            return ManifestValidationIssue::warning(
                $moduleName,
                $toolId,
                "Class \"{$class}\" has no execute() method."
            );
        }

        try {
            $reflection  = new ReflectionMethod($class, 'execute');
            $returnType  = $reflection->getReturnType();

            if ($returnType === null) {
                return ManifestValidationIssue::warning(
                    $moduleName,
                    $toolId,
                    "execute() on \"{$class}\" has no declared return type (expected: array)."
                );
            }

            if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'array') {
                return null;
            }

            return ManifestValidationIssue::error(
                $moduleName,
                $toolId,
                "execute() on \"{$class}\" must return array, got: \"{$returnType}\"."
            );
        } catch (\ReflectionException $e) {
            return ManifestValidationIssue::warning(
                $moduleName,
                $toolId,
                "Could not reflect execute() on \"{$class}\": ".$e->getMessage()
            );
        }
    }

    /**
     * Run validateAll(), log any errors, and return true if there are failures.
     *
     * Called during boot to perform a non-fatal check. Critical issues are
     * logged so that operators are alerted without crashing the application.
     */
    public function bootCheck(): bool
    {
        $issues = $this->validateAll();

        $errors = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        if (empty($errors)) {
            return false;
        }

        foreach ($errors as $issue) {
            Log::critical('TitanCore.AI.ManifestValidator: '.$issue->message(), [
                'module' => $issue->module(),
                'tool'   => $issue->tool(),
            ]);
        }

        return true;
    }
}
