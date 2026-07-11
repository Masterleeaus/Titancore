<?php

namespace Modules\TitanCore\Services\Upgrade;

/**
 * Validates that all modules declared in a manifest's `requires` array
 * are present and (if a version constraint is given) satisfy it.
 *
 * module.json examples:
 *   "requires": ["TitanCore", "Accountings"]          ← name-only
 *   "requires": { "TitanCore": "^1.0", "Accountings": "*" }  ← with constraint
 */
class DependencyChecker
{
    private VersionCompatibilityChecker $versionChecker;

    /** Base path where module directories live. */
    private string $modulesBase;

    public function __construct(?VersionCompatibilityChecker $versionChecker = null, ?string $modulesBase = null)
    {
        $this->versionChecker = $versionChecker ?? new VersionCompatibilityChecker();
        $this->modulesBase    = $modulesBase ?? base_path('Modules');
    }

    /**
     * @param  array<string, mixed>  $manifest  Decoded module.json
     * @return array{ok: bool, errors: string[]}
     */
    public function check(array $manifest): array
    {
        $errors   = [];
        $requires = $manifest['requires'] ?? [];

        // Normalise to ['ModuleName' => 'constraint'] map
        $deps = $this->normalise($requires);

        foreach ($deps as $moduleName => $constraint) {
            $depManifest = $this->loadManifest($moduleName);

            if ($depManifest === null) {
                $errors[] = "Required module \"{$moduleName}\" is not installed (module.json not found).";
                continue;
            }

            if ($constraint !== '*' && $constraint !== '') {
                $depVersion = $depManifest['version'] ?? '0.0.0';
                $result     = $this->versionChecker->satisfies($depVersion, $constraint);

                if (! $result) {
                    $errors[] = sprintf(
                        'Module "%s" version %s does not satisfy required constraint "%s".',
                        $moduleName,
                        $depVersion,
                        $constraint
                    );
                }
            }
        }

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<int|string, string>|string[]  $requires
     * @return array<string, string>
     */
    private function normalise(array $requires): array
    {
        $map = [];

        foreach ($requires as $key => $value) {
            if (is_int($key)) {
                // List form: ["TitanCore", "Accountings"]
                $map[(string) $value] = '*';
            } else {
                // Map form: {"TitanCore": "^1.0"}
                $map[(string) $key] = (string) $value;
            }
        }

        return $map;
    }

    /**
     * Load and decode module.json for the given module name.
     *
     * @return array<string, mixed>|null
     */
    private function loadManifest(string $moduleName): ?array
    {
        $path = $this->modulesBase . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'module.json';

        if (! file_exists($path)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
