<?php

namespace Modules\TitanCore\Support\Filament;

/**
 * DeveloperToolsFramework — metadata registry for the Developer Studio tools.
 *
 * Each tool is identified by a page key (matching the panel navigation config)
 * and carries metadata such as label, description, group and whether it is
 * currently available.
 *
 * Engine module providers can register additional tools at boot so that
 * the Developer Studio remains extensible without modifying TitanCore.
 */
class DeveloperToolsFramework
{
    /** @var array<string, array<string, mixed>> Keyed by tool page key. */
    private array $tools = [];

    /**
     * Register a developer tool.
     *
     * @param  array<string, mixed>  $attributes  Should include 'label', optionally 'description', 'group', 'available'.
     */
    public function register(string $pageKey, array $attributes): void
    {
        $this->tools[$pageKey] = [
            'key'         => $pageKey,
            'label'       => (string) ($attributes['label'] ?? $pageKey),
            'description' => (string) ($attributes['description'] ?? ''),
            'group'       => (string) ($attributes['group'] ?? ''),
            'available'   => (bool) ($attributes['available'] ?? true),
        ];
    }

    /**
     * Return all registered tool definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * Return the definition for a single tool, or null when not registered.
     *
     * @return array<string, mixed>|null
     */
    public function tool(string $pageKey): ?array
    {
        return $this->tools[$pageKey] ?? null;
    }

    /**
     * Return only the available (enabled) tools.
     *
     * @return array<string, array<string, mixed>>
     */
    public function availableTools(): array
    {
        return array_filter($this->tools, fn (array $t) => $t['available']);
    }

    /**
     * Return tools grouped by their 'group' attribute.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function toolsByGroup(): array
    {
        $groups = [];

        foreach ($this->tools as $key => $tool) {
            $group            = $tool['group'] ?: '';
            $groups[$group][$key] = $tool;
        }

        return $groups;
    }

    /**
     * Check whether a tool is registered.
     */
    public function has(string $pageKey): bool
    {
        return isset($this->tools[$pageKey]);
    }

    /**
     * Mark a tool as unavailable (e.g., when a required dependency is missing).
     */
    public function disable(string $pageKey): void
    {
        if (isset($this->tools[$pageKey])) {
            $this->tools[$pageKey]['available'] = false;
        }
    }

    /**
     * Return all registered page keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->tools);
    }
}
