<?php

namespace Modules\TitanCore\Support;

use Composer\Semver\Semver;
use Nwidart\Modules\Contracts\RepositoryInterface;

/**
 * Builds and queries a directed dependency graph for all installed Titan modules.
 *
 * Dependency declarations are read from each module's module.json:
 *   - requires  : modules (or Composer packages) this module depends on
 *   - conflicts : modules that cannot be enabled alongside this module
 *   - suggests  : optional / recommended modules
 *   - replaces  : modules that this module supersedes
 *
 * A "requires" entry may be:
 *   - "ModuleName"            – simple name dependency
 *   - "ModuleName:^1.2.0"    – name + semver constraint (colon separator)
 *   - "vendor/package"        – Composer package (not a Titan module; noted but not blocked)
 */
class ModuleDependencyGraph
{
    /** Placeholder version used when a module declares no version. */
    public const DEFAULT_VERSION = '0.0.0';

    /**
     * @var array<string, array{
     *     name: string,
     *     version: string,
     *     requires: list<array{name: string, constraint: string|null}>,
     *     conflicts: list<string>,
     *     suggests: list<string>,
     *     replaces: list<string>,
     *     enabled: bool
     * }>
     */
    protected array $nodes = [];

    public function __construct(protected RepositoryInterface $modules) {}

    // ─── Graph construction ───────────────────────────────────────────────────

    /**
     * Build (or rebuild) the dependency graph from all discovered modules.
     */
    public function build(): static
    {
        $this->nodes = [];

        foreach ($this->modules->all() as $module) {
            $name = $module->getName();
            $this->nodes[$name] = [
                'name' => $name,
                'version' => (string) ($module->get('version') ?: self::DEFAULT_VERSION),
                'requires' => $this->parseRequires((array) ($module->get('requires') ?? [])),
                'conflicts' => $this->normaliseList((array) ($module->get('conflicts') ?? [])),
                'suggests' => $this->normaliseList((array) ($module->get('suggests') ?? [])),
                'replaces' => $this->normaliseList((array) ($module->get('replaces') ?? [])),
                'enabled' => $module->isEnabled(),
            ];
        }

        return $this;
    }

    /**
     * Inject synthetic nodes (primarily for testing without a real module repository).
     *
     * @param  array<string, array{version?: string, requires?: array, conflicts?: array, suggests?: array, replaces?: array, enabled?: bool}>  $nodes
     */
    public function setNodes(array $nodes): static
    {
        $this->nodes = [];

        foreach ($nodes as $name => $data) {
            $this->nodes[$name] = [
                'name' => $name,
                'version' => (string) ($data['version'] ?? self::DEFAULT_VERSION),
                'requires' => $this->parseRequires((array) ($data['requires'] ?? [])),
                'conflicts' => $this->normaliseList((array) ($data['conflicts'] ?? [])),
                'suggests' => $this->normaliseList((array) ($data['suggests'] ?? [])),
                'replaces' => $this->normaliseList((array) ($data['replaces'] ?? [])),
                'enabled' => (bool) ($data['enabled'] ?? true),
            ];
        }

        return $this;
    }

    // ─── Load-order resolver (Kahn's algorithm) ───────────────────────────────

    /**
     * Produce a deterministic, dependency-respecting load order for all modules.
     *
     * Modules with no inter-module requirements appear first.  If the graph
     * contains cycles the cyclic modules are appended at the end (they will be
     * separately reported by {@see detectCycles()}).
     *
     * @return string[] Module names in safe boot order.
     */
    public function resolveLoadOrder(): array
    {
        $moduleNames = array_keys($this->nodes);

        // in-degree per node
        $inDegree = array_fill_keys($moduleNames, 0);
        // adjacency: dependency → list of dependents
        $adj = array_fill_keys($moduleNames, []);

        foreach ($this->nodes as $name => $node) {
            foreach ($node['requires'] as $req) {
                $dep = $req['name'];
                if (! $this->isModuleDep($dep)) {
                    continue;
                }
                if (! isset($this->nodes[$dep])) {
                    continue; // missing dep – reported elsewhere
                }
                $adj[$dep][] = $name;
                $inDegree[$name]++;
            }
        }

        // Seed queue with zero-in-degree nodes (sorted for determinism)
        $queue = array_values(array_filter($moduleNames, fn ($n) => $inDegree[$n] === 0));
        sort($queue);

        $order = [];

        while (! empty($queue)) {
            $current = array_shift($queue);
            $order[] = $current;

            $neighbors = $adj[$current];
            sort($neighbors);

            foreach ($neighbors as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // Append any remaining nodes that are part of cycles
        $remaining = array_values(array_filter($moduleNames, fn ($n) => ! in_array($n, $order, true)));
        sort($remaining);

        return array_merge($order, $remaining);
    }

    // ─── Circular dependency detector ────────────────────────────────────────

    /**
     * Detect cycles in the dependency graph using iterative DFS.
     *
     * @return list<list<string>> Each entry is a list of module names that form one cycle.
     */
    public function detectCycles(): array
    {
        $visited = [];
        $inStack = [];
        $cycles = [];

        foreach (array_keys($this->nodes) as $start) {
            if (isset($visited[$start])) {
                continue;
            }

            // Stack entries: [node, iterator-index, path-so-far]
            $stack = [[$start, 0, [$start]]];
            $inStack = [$start => true];

            while (! empty($stack)) {
                [, $idx, $path] = end($stack);
                $node = $path[count($path) - 1];
                $deps = $this->moduleDepNames($this->nodes[$node]['requires'] ?? []);

                if ($idx < count($deps)) {
                    // Advance iterator
                    $top = array_pop($stack);
                    $top[1]++;
                    $stack[] = $top;

                    $dep = $deps[$idx];

                    if (! isset($this->nodes[$dep])) {
                        continue;
                    }

                    if (isset($inStack[$dep]) && $inStack[$dep]) {
                        // Found a back-edge → cycle
                        $cycleStart = array_search($dep, $path, true);
                        $cycle = array_slice($path, $cycleStart);
                        $cycle[] = $dep;
                        $cycles[] = $cycle;

                        continue;
                    }

                    if (! isset($visited[$dep])) {
                        $newPath = $path;
                        $newPath[] = $dep;
                        $stack[] = [$dep, 0, $newPath];
                        $inStack[$dep] = true;
                    }
                } else {
                    // Backtrack
                    array_pop($stack);
                    $visited[$node] = true;
                    unset($inStack[$node]);
                }
            }
        }

        return $cycles;
    }

    // ─── Enable-time validation ───────────────────────────────────────────────

    /**
     * Validate that enabling the given module is safe.
     *
     * Checks:
     *  - All `requires` entries that are Titan modules exist and are enabled.
     *  - Semver version constraints in `requires` are satisfied.
     *  - No `conflicts` entry is currently enabled.
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validateEnableModule(string $moduleName): array
    {
        $errors = [];
        $warnings = [];

        if (! isset($this->nodes[$moduleName])) {
            $errors[] = "Module '{$moduleName}' is not installed.";

            return compact('errors', 'warnings');
        }

        $node = $this->nodes[$moduleName];

        // ── requires ─────────────────────────────────────────────────────────
        foreach ($node['requires'] as $req) {
            $dep = $req['name'];

            if (! $this->isModuleDep($dep)) {
                // Composer package dependency – cannot check here
                continue;
            }

            if (! isset($this->nodes[$dep])) {
                $errors[] = "Required module '{$dep}' is not installed.";

                continue;
            }

            if (! $this->nodes[$dep]['enabled']) {
                $errors[] = "Required module '{$dep}' is disabled. Enable it before enabling '{$moduleName}'.";

                continue;
            }

            // Version constraint
            if ($req['constraint'] !== null) {
                $depVersion = $this->nodes[$dep]['version'];
                if ($depVersion === self::DEFAULT_VERSION || $depVersion === '') {
                    $warnings[] = "Module '{$dep}' has no declared version; cannot validate constraint '{$req['constraint']}'.";
                } else {
                    try {
                        if (! Semver::satisfies($depVersion, $req['constraint'])) {
                            $errors[] = "Module '{$dep}' (version {$depVersion}) does not satisfy required constraint '{$req['constraint']}'.";
                        }
                    } catch (\Exception $e) {
                        $warnings[] = "Could not parse version constraint '{$req['constraint']}' for '{$dep}': {$e->getMessage()}";
                    }
                }
            }
        }

        // ── conflicts ────────────────────────────────────────────────────────
        foreach ($node['conflicts'] as $conflictName) {
            if (! $this->isModuleDep($conflictName)) {
                continue;
            }
            if (isset($this->nodes[$conflictName]) && $this->nodes[$conflictName]['enabled']) {
                $errors[] = "Module '{$conflictName}' conflicts with '{$moduleName}' and is currently enabled.";
            }
        }

        return compact('errors', 'warnings');
    }

    /**
     * Gather dependency issues for every installed module.
     *
     * @return array<string, array{errors: list<string>, warnings: list<string>}>
     */
    public function getAllIssues(): array
    {
        $issues = [];

        foreach (array_keys($this->nodes) as $name) {
            $result = $this->validateEnableModule($name);
            if (! empty($result['errors']) || ! empty($result['warnings'])) {
                $issues[$name] = $result;
            }
        }

        return $issues;
    }

    // ─── Dependency-tree renderer ─────────────────────────────────────────────

    /**
     * Build a recursive dependency tree for the given module.
     *
     * @param  string[]  $seen  Used internally to detect circular references.
     * @return array{name: string, version: string, enabled: bool, depth: int, circular?: true, composer_deps?: list<array{name: string, constraint: string|null}>, children: list<mixed>}
     */
    public function getDependencyTree(string $moduleName, int $depth = 0, array $seen = []): array
    {
        if (! isset($this->nodes[$moduleName])) {
            return [
                'name' => $moduleName,
                'version' => '?',
                'enabled' => false,
                'depth' => $depth,
                'missing' => true,
                'children' => [],
            ];
        }

        $node = $this->nodes[$moduleName];

        $tree = [
            'name' => $moduleName,
            'version' => $node['version'],
            'enabled' => $node['enabled'],
            'depth' => $depth,
            'children' => [],
        ];

        if (in_array($moduleName, $seen, true)) {
            $tree['circular'] = true;

            return $tree;
        }

        $seen[] = $moduleName;

        foreach ($node['requires'] as $req) {
            $dep = $req['name'];

            if (! $this->isModuleDep($dep)) {
                $tree['composer_deps'][] = ['name' => $dep, 'constraint' => $req['constraint']];

                continue;
            }

            $tree['children'][] = $this->getDependencyTree($dep, $depth + 1, $seen);
        }

        return $tree;
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Parse a raw `requires` array into structured entries.
     *
     * @param  array<int, mixed>  $requires
     * @return list<array{name: string, constraint: string|null}>
     */
    protected function parseRequires(array $requires): array
    {
        $parsed = [];

        foreach ($requires as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            if (str_contains($entry, ':')) {
                [$name, $constraint] = explode(':', $entry, 2);
                $parsed[] = ['name' => trim($name), 'constraint' => trim($constraint)];
            } else {
                $parsed[] = ['name' => trim($entry), 'constraint' => null];
            }
        }

        return $parsed;
    }

    /**
     * Normalise a simple string list (conflicts / suggests / replaces).
     *
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    protected function normaliseList(array $items): array
    {
        return array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $items)));
    }

    /**
     * Return true when the dependency name refers to a Titan module
     * (not a Composer package like "vendor/package").
     */
    protected function isModuleDep(string $name): bool
    {
        return ! str_contains($name, '/');
    }

    /**
     * Extract only the Titan module names from a parsed requires list.
     *
     * @param  list<array{name: string, constraint: string|null}>  $requires
     * @return list<string>
     */
    protected function moduleDepNames(array $requires): array
    {
        return array_values(
            array_map(
                fn ($r) => $r['name'],
                array_filter($requires, fn ($r) => $this->isModuleDep($r['name'])),
            )
        );
    }
}
