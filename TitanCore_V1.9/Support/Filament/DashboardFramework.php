<?php

namespace Modules\TitanCore\Support\Filament;

/**
 * DashboardFramework — reusable dashboard system for all Studio panels.
 *
 * Provides metadata about widget types and the widget lists for each
 * configured dashboard. Widget instances are resolved by the consumer;
 * this class is purely a metadata / configuration layer.
 */
class DashboardFramework
{
    /** @var array<string, array<string, mixed>> */
    private array $widgetTypes;

    /** @var array<string, array<string, mixed>> */
    private array $dashboards;

    /**
     * @param  array<string, mixed>  $config  The 'titancore.filament.dashboard_framework' config slice.
     */
    public function __construct(array $config = [])
    {
        $this->widgetTypes = (array) ($config['widget_types'] ?? []);
        $this->dashboards  = (array) ($config['dashboards'] ?? []);
    }

    /**
     * Return the widget keys registered for a dashboard.
     *
     * @return string[]
     */
    public function widgets(string $dashboardKey): array
    {
        return (array) ($this->dashboards[$dashboardKey]['widgets'] ?? []);
    }

    /**
     * Return all registered widget type definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function widgetTypes(): array
    {
        return $this->widgetTypes;
    }

    /**
     * Return the definition for a single widget type, or an empty array when unknown.
     *
     * @return array<string, mixed>
     */
    public function widgetType(string $key): array
    {
        return (array) ($this->widgetTypes[$key] ?? []);
    }

    /**
     * Check whether a dashboard key is registered.
     */
    public function hasDashboard(string $key): bool
    {
        return isset($this->dashboards[$key]);
    }

    /**
     * Check whether a widget type is resizable.
     */
    public function isResizable(string $widgetTypeKey): bool
    {
        return (bool) ($this->widgetTypes[$widgetTypeKey]['resizable'] ?? false);
    }

    /**
     * Return all registered dashboard keys.
     *
     * @return string[]
     */
    public function dashboardKeys(): array
    {
        return array_keys($this->dashboards);
    }
}
