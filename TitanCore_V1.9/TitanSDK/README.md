# TitanSDK

TitanSDK is the stable public integration layer extracted from TitanCore.
TitanCore remains the Platform Kernel implementation; TitanSDK contains only the public surface that Titan modules are expected to consume.

## Directory structure

- `src/Contracts/AI` — extracted public AI contracts
- `src/Events` — extracted public events
- `src/Exceptions/AI` — extracted public SDK exceptions
- `src/ValueObjects` — extracted public value objects
- `src/Facades` — stable SDK facade wrappers
- `src/Providers` — SDK service provider
- `manifests` — canonical public manifest files
- `config` — SDK bootstrap configuration

## Extracted classes

### Contracts
- `TitanSDK\Contracts\AI\ChatProviderContract`
- `TitanSDK\Contracts\AI\CitationContract`
- `TitanSDK\Contracts\AI\ClientInterface`
- `TitanSDK\Contracts\AI\EmbeddingProviderContract`
- `TitanSDK\Contracts\AI\GuardrailContract`
- `TitanSDK\Contracts\AI\IndexingContract`
- `TitanSDK\Contracts\AI\RetrievalContract`
- `TitanSDK\Contracts\AI\ToolExecutorContract`
- `TitanSDK\Contracts\AI\ToolRollbackContract`
- `TitanSDK\Contracts\AI\VectorStoreContract`

### Events
- `TitanSDK\Events\AiRequestCompleted`

### Exceptions
- `TitanSDK\Exceptions\AI\ToolHandlerNotFoundException`
- `TitanSDK\Exceptions\AI\ToolInputValidationException`
- `TitanSDK\Exceptions\AI\ToolNotAllowedException`
- `TitanSDK\Exceptions\AI\ToolPermissionDeniedException`
- `TitanSDK\Exceptions\AI\ToolRecursionDetectedException`
- `TitanSDK\Exceptions\AI\ToolTimedOutException`

### Value objects and facades
- `TitanSDK\ValueObjects\ToolContext`
- `TitanSDK\ValueObjects\ToolResult`
- `TitanSDK\Facades\TitanAI`
- `TitanSDK\Providers\TitanSdkServiceProvider`

## Remaining internal TitanCore classes

The following implementation classes intentionally remain inside TitanCore:

- `Modules\TitanCore\Services\TitanCoreModelGateway`
- `Modules\TitanCore\Services\TitanCoreAIService`
- `Modules\TitanCore\AI\ToolExecutor`
- `Modules\TitanCore\Services\ProviderFailoverChain`
- `Modules\TitanCore\AI\Providers\*`
- `Modules\TitanCore\AI\VectorStore\*`
- `Modules\TitanCore\Http\Controllers\*`
- `Modules\TitanCore\Providers\TitanCoreServiceProvider`
- `Modules\TitanCore\Providers\TitanCorePlatformIntegrationServiceProvider`

These remain internal because they contain runtime orchestration, provider integration, controller behavior, discovery implementation, or other Platform Kernel internals that must not leak into the SDK package.

## Namespace mapping

- `Modules\TitanCore\Contracts\AI\*` → `TitanSDK\Contracts\AI\*`
- `Modules\TitanCore\Events\AiRequestCompleted` → `TitanSDK\Events\AiRequestCompleted`
- `Modules\TitanCore\Exceptions\AI\*` → `TitanSDK\Exceptions\AI\*`
- `Modules\TitanCore\AI\ValueObjects\ToolContext` → `TitanSDK\ValueObjects\ToolContext`
- `Modules\TitanCore\AI\ValueObjects\ToolResult` → `TitanSDK\ValueObjects\ToolResult`

Legacy TitanCore classes remain in place as compatibility layers that extend or mirror the canonical TitanSDK types.

## Composer configuration

The SDK package is published from `TitanSDK/composer.json` with PSR-4 autoloading for the `TitanSDK\` namespace and Laravel auto-discovery for `TitanSDK\Providers\TitanSdkServiceProvider`.

## TitanCore compatibility changes

- TitanCore continues to expose the existing `Modules\TitanCore\...` public classes.
- Legacy public contracts now extend the corresponding TitanSDK contracts.
- Legacy event and public exceptions now extend the corresponding TitanSDK classes.
- `Modules\TitanCore\AI\ValueObjects\ToolContext` now extends `TitanSDK\ValueObjects\ToolContext`.
- `Modules\TitanCore\AI\ValueObjects\ToolResult` now extends `TitanSDK\ValueObjects\ToolResult`.

## Items intentionally left inside TitanCore

- Runtime services and provider resolution
- Controllers and HTTP endpoints
- Tool execution implementation
- Discovery and registry implementation details
- Vector store implementations
- Provider adapters and HTTP clients
- Database, jobs, policies, and business-adjacent manifests
