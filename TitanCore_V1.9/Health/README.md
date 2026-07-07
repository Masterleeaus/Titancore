# TitanCore Health Contract

This folder is used by the Titan module manager to report module health without crashing Filament panels.

## Default checks

- provider_exists
- filament_resources_valid
- filament_pages_valid
- translations_loaded
- routes_present
- migrations_present
- manifest_valid

Module-specific checks may be added to `checks.php`.
