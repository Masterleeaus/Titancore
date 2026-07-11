# Changelog

## 1.0.1
- Certification sprint: synced `module.json` to include `filament_panels` declaration, matching the TitanCore source.
- Added `version` field to `composer.json` for semantic versioning compliance.
- Added optional `correlationId` to engine events (`EngineInstalled`, `EngineLifecycleChanged`, `EngineValidated`) for full SDK event certification.
- Updated `README.md` to document all four public SDK events and all Engine contracts.
- Extended namespace mapping in `README.md` to include Engine contracts and engine events.

## 1.0.0
- Initial TitanSDK extraction from TitanCore public APIs.
- Added canonical AI contracts, SDK event, public exceptions, ToolContext and ToolResult value objects, manifests, TitanAI facade, and TitanSdkServiceProvider.
