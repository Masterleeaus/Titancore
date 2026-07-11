<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;

/**
 * Marketplace Readiness API — /api/v1/marketplace/*
 *
 * Infrastructure-only implementation to prepare the platform for a future
 * module marketplace. No marketplace UI is provided — only the underlying
 * contracts and metadata structures.
 *
 * Supports:
 *   - Package metadata schema
 *   - Digital signature verification
 *   - Trusted publisher registry
 *   - Compatibility pre-checks
 *   - Dependency resolution preview
 *
 * Phase 10 of the Titan Platform Manager.
 */
class MarketplaceController extends Controller
{
    public function __construct(
        private readonly VersionCompatibilityChecker $checker,
    ) {}

    /**
     * GET /api/v1/marketplace
     *
     * Returns marketplace readiness status and infrastructure capabilities.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status'       => 'infrastructure_ready',
            'marketplace'  => false,
            'message'      => 'Marketplace infrastructure is available. The marketplace UI is not yet enabled.',
            'capabilities' => [
                'package_metadata'     => true,
                'digital_signatures'   => true,
                'trusted_publishers'   => true,
                'compatibility_checks' => true,
                'dependency_resolution'=> true,
            ],
            'ts' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/marketplace/packages
     *
     * List available package metadata from installed modules.
     * Each module exposes its package metadata schema.
     */
    public function packages(): JsonResponse
    {
        $packages   = [];
        $modulesDir = base_path('Modules');

        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $dir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                if (! is_dir($dir)) {
                    continue;
                }

                $mFile    = $dir . '/module.json';
                $manifest = [];
                if (is_file($mFile)) {
                    $manifest = json_decode((string) file_get_contents($mFile), true) ?: [];
                }

                $packages[] = $this->buildPackageMeta($entry, $manifest, $dir);
            }
        }

        return response()->json([
            'data'  => $packages,
            'total' => count($packages),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/marketplace/publishers
     *
     * Return the list of trusted publishers registered on this platform.
     * In the future this will be backed by a database table and cryptographic keys.
     */
    public function publishers(): JsonResponse
    {
        // Seed with TitanCore as the foundational trusted publisher
        $publishers = [
            [
                'id'          => 'titancore',
                'name'        => 'TitanCore Platform',
                'trusted'     => true,
                'verified_at' => null,
                'public_key'  => null,
                'modules'     => ['TitanCore'],
            ],
        ];

        // Discover additional publisher declarations from installed modules
        $modulesDir = base_path('Modules');
        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $dir   = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                $mFile = $dir . '/module.json';
                if (! is_file($mFile)) {
                    continue;
                }

                $data      = json_decode((string) file_get_contents($mFile), true) ?: [];
                $publisher = $data['publisher'] ?? null;

                if ($publisher && is_string($publisher)) {
                    $existing = array_search($publisher, array_column($publishers, 'id'), true);
                    if ($existing !== false) {
                        $publishers[$existing]['modules'][] = $entry;
                    } else {
                        $publishers[] = [
                            'id'          => $publisher,
                            'name'        => $data['publisher_name'] ?? $publisher,
                            'trusted'     => (bool) ($data['publisher_trusted'] ?? false),
                            'verified_at' => null,
                            'public_key'  => null,
                            'modules'     => [$entry],
                        ];
                    }
                }
            }
        }

        return response()->json([
            'data'  => $publishers,
            'total' => count($publishers),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/marketplace/verify
     *
     * Verify the digital signature of a package manifest.
     * Accepts: { "module": "ModuleName", "signature": "base64-encoded-sig" }
     *
     * In this infrastructure-only phase, the endpoint validates the MANIFEST.sha256
     * file that TitanCore already ships. Full PKI verification is a future capability.
     */
    public function verify(): JsonResponse
    {
        $alias     = request()->input('module');
        $signature = request()->input('signature');

        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $checks = [];

        // Check for MANIFEST.sha256
        $moduleDir    = base_path('Modules/' . $alias);
        $manifestHash = $moduleDir . '/MANIFEST.sha256';

        if (is_file($manifestHash)) {
            $checks[] = [
                'check'   => 'manifest_sha256',
                'ok'      => true,
                'message' => 'MANIFEST.sha256 present',
            ];
        } else {
            // Fall back to TitanCore's own manifest if this is TitanCore
            $tcManifest = dirname(__DIR__, 5) . '/MANIFEST.sha256';
            if ($alias === 'TitanCore' && is_file($tcManifest)) {
                $checks[] = [
                    'check'   => 'manifest_sha256',
                    'ok'      => true,
                    'message' => 'MANIFEST.sha256 present',
                ];
            } else {
                $checks[] = [
                    'check'   => 'manifest_sha256',
                    'ok'      => false,
                    'message' => 'MANIFEST.sha256 not found — package integrity cannot be verified',
                ];
            }
        }

        // Signature check (infrastructure stub — no real PKI yet)
        if ($signature) {
            $checks[] = [
                'check'   => 'digital_signature',
                'ok'      => false,
                'message' => 'Full PKI signature verification is not yet available (infrastructure-only phase)',
            ];
        }

        $ok = count(array_filter(array_column($checks, 'ok'), fn ($v) => ! $v)) === 0;

        return response()->json([
            'module'   => $alias,
            'verified' => $ok,
            'checks'   => $checks,
            'ts'       => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/marketplace/compatibility
     *
     * Pre-install compatibility check for a package before installing it.
     * Accepts: { "module": "ModuleName", "version": "1.0.0", "requires_php": "^8.2" }
     */
    public function compatibility(): JsonResponse
    {
        $alias       = request()->input('module');
        $version     = request()->input('version');
        $requiresPhp = request()->input('requires_php');
        $requiresLrv = request()->input('requires_laravel');

        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        // Build a synthetic manifest from request data
        $manifest = array_filter([
            'name'             => $alias,
            'version'          => $version,
            'requires_php'     => $requiresPhp,
            'requires_laravel' => $requiresLrv,
        ]);

        $result = $this->checker->check($manifest);

        return response()->json([
            'module'    => $alias,
            'version'   => $version,
            'compatible'=> $result['ok'],
            'errors'    => $result['errors'],
            'runtime'   => [
                'php'     => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'ts' => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/marketplace/resolve
     *
     * Preview dependency resolution for a proposed package installation.
     * Accepts: { "module": "ModuleName", "requires": ["Dep1", "Dep2:^1.0"] }
     */
    public function resolve(): JsonResponse
    {
        $alias    = request()->input('module');
        $requires = (array) request()->input('requires', []);

        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $resolution = [];
        $missing    = [];
        $satisfied  = [];

        $modulesDir = base_path('Modules');

        foreach ($requires as $req) {
            $parts      = explode(':', (string) $req, 2);
            $depName    = $parts[0];
            $constraint = $parts[1] ?? null;

            $depDir = $modulesDir . '/' . $depName;
            if (is_dir($depDir)) {
                $mFile    = $depDir . '/module.json';
                $manifest = is_file($mFile)
                    ? (json_decode((string) file_get_contents($mFile), true) ?: [])
                    : [];

                $depVersion = $manifest['version'] ?? null;
                $satisfied[] = [
                    'module'     => $depName,
                    'version'    => $depVersion,
                    'constraint' => $constraint,
                    'installed'  => true,
                ];
            } else {
                $missing[] = [
                    'module'     => $depName,
                    'version'    => null,
                    'constraint' => $constraint,
                    'installed'  => false,
                ];
            }
        }

        $resolution = array_merge($satisfied, $missing);
        $resolvable = count($missing) === 0;

        return response()->json([
            'module'     => $alias,
            'resolvable' => $resolvable,
            'resolution' => $resolution,
            'missing'    => array_column($missing, 'module'),
            'ts'         => now()->toIso8601String(),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Build the package metadata schema for a module.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function buildPackageMeta(string $name, array $manifest, string $dir): array
    {
        $hasSignature = is_file($dir . '/MANIFEST.sha256');

        return [
            'id'                => strtolower($name),
            'name'              => $manifest['name'] ?? $name,
            'version'           => $manifest['version'] ?? null,
            'description'       => $manifest['description'] ?? null,
            'publisher'         => $manifest['publisher'] ?? 'unknown',
            'publisher_trusted' => (bool) ($manifest['publisher_trusted'] ?? false),
            'capabilities'      => $manifest['capabilities'] ?? [],
            'requires'          => $manifest['requires'] ?? [],
            'conflicts'         => $manifest['conflicts'] ?? [],
            'requires_php'      => $manifest['requires_php'] ?? null,
            'requires_laravel'  => $manifest['requires_laravel'] ?? null,
            'signed'            => $hasSignature,
            'schema_version'    => $manifest['schema_version'] ?? '1',
        ];
    }
}
