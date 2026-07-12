<?php

namespace Modules\TitanCore\Services;

use Modules\TitanCore\Services\Upgrade\DependencyChecker;
use Modules\TitanCore\Services\Upgrade\UpgradeEngine;
use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;

/**
 * UpgradeCentre — Platform Studio facade over the upgrade infrastructure.
 *
 * Provides high-level access to:
 *  - Available updates
 *  - Installed versions
 *  - Upgrade history
 *  - Compatibility checks
 *  - Pre-flight validation
 *  - Upgrade execution (delegates to UpgradeEngine)
 *  - Rollback
 *
 * The centre does not own upgrade logic — it coordinates existing services.
 */
class UpgradeCentre
{
    private UpgradeEngine $engine;
    private VersionCompatibilityChecker $versionChecker;
    private DependencyChecker $dependencyChecker;

    /** @var string Base path where module directories live. */
    private string $modulesBase;

    public function __construct(
        ?UpgradeEngine $engine = null,
        ?VersionCompatibilityChecker $versionChecker = null,
        ?DependencyChecker $dependencyChecker = null,
        ?string $modulesBase = null,
    ) {
        $this->engine            = $engine            ?? new UpgradeEngine();
        $this->versionChecker    = $versionChecker    ?? new VersionCompatibilityChecker();
        $this->dependencyChecker = $dependencyChecker ?? new DependencyChecker($this->versionChecker);
        $this->modulesBase       = $modulesBase       ?? (function_exists('base_path') ? base_path('Modules') : '');
    }

    /**
     * Run the full upgrade pipeline for a module.
     *
     * @return array{ok: bool, dry_run: bool, module: string, steps: array, snapshot_dir: string|null, errors: string[]}
     */
    public function upgrade(string $moduleName, bool $dryRun = false): array
    {
        return $this->engine
            ->setDryRun($dryRun)
            ->run($moduleName);
    }

    /**
     * Run pre-flight validation for a module without executing any upgrade steps.
     *
     * Returns version and dependency check results.
     *
     * @return array{ok: bool, module: string, checks: array<string, array{ok: bool, errors: string[]}>}
     */
    public function preflight(string $moduleName): array
    {
        $manifest = $this->loadManifest($moduleName);

        $versionResult = $this->versionChecker->check($manifest);
        $depResult     = $this->dependencyChecker->check($manifest);

        $ok = $versionResult['ok'] && $depResult['ok'];

        return [
            'ok'     => $ok,
            'module' => $moduleName,
            'checks' => [
                'version'      => $versionResult,
                'dependencies' => $depResult,
            ],
        ];
    }

    /**
     * Return the installed version of a module from its manifest, or null when not found.
     */
    public function installedVersion(string $moduleName): ?string
    {
        $manifest = $this->loadManifest($moduleName);

        return isset($manifest['version']) ? (string) $manifest['version'] : null;
    }

    /**
     * Return the manifest data for a module, or an empty array when not found.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $moduleName): array
    {
        return $this->loadManifest($moduleName);
    }

    /**
     * Return a compatibility check result for a module's declared constraints.
     *
     * @return array{ok: bool, errors: string[]}
     */
    public function compatibilityCheck(string $moduleName): array
    {
        return $this->versionChecker->check($this->loadManifest($moduleName));
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $moduleName): array
    {
        $path = rtrim($this->modulesBase, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $moduleName
            . DIRECTORY_SEPARATOR . 'module.json';

        if (! file_exists($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
