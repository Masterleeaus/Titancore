GitHub Copilot Instructions — TitanCore Platform Kernel

Purpose

TitanCore is not an application.

TitanCore is the Platform Kernel of the Titan ecosystem.

It provides the shared runtime, SDK, contracts, discovery, orchestration and platform services upon which all Titan modules depend.

Business logic does not belong inside TitanCore.

⸻

Your Role

When editing this repository, act as a senior implementation engineer, not a software architect.

Your responsibilities are:

* implement approved architectural decisions
* preserve platform boundaries
* maintain backwards compatibility
* improve code quality
* reduce technical debt
* strengthen consistency

Do not redesign the architecture.

Do not invent new platform concepts.

If architectural uncertainty exists, report it rather than making assumptions.

⸻

Working Process

Always follow this sequence.

1. Discover

Inspect the repository.

Gather evidence.

Understand existing implementation.

Never assume.

⸻

2. Report

If requested, report findings.

Every finding must include:

* evidence
* affected files
* impact
* confidence

Do not speculate.

⸻

3. Implement

Only implement approved changes.

Keep changes as small as possible.

Preserve existing behaviour.

⸻

4. Validate

After every change:

* run relevant tests
* lint modified files
* verify backwards compatibility
* run static analysis when available
* check for security issues

Never consider work complete until validation passes.

⸻

Platform Identity

TitanCore owns platform responsibilities only.

Examples include:

* Runtime
* AI Gateway
* Provider Resolution
* Discovery
* Registry
* Configuration
* Knowledge Runtime
* Tool Runtime
* Workflow Runtime
* Agent Runtime
* SDK
* Contracts
* Administration
* Telemetry
* Observability
* Security

Business modules own:

* CRM
* Cleaning
* Dispatch
* Sales
* Finance
* Booking
* Industry logic

TitanCore must remain platform-agnostic.

⸻

Architectural Rules

Always preserve these rules.

Business Logic

Never introduce business-specific logic into TitanCore.

⸻

Runtime

All AI execution must pass through:

TitanCoreModelGateway

Never bypass the gateway.

Never call providers directly from controllers or modules.

⸻

Contracts

Prefer:

Contracts

↓

Services

↓

Gateway

↓

Provider

Avoid concrete dependencies.

⸻

Dependency Injection

Always use container resolution.

Avoid manual instantiation.

Avoid:

new Provider()

Prefer dependency injection and container bindings.

⸻

Discovery

Use metadata-driven discovery.

Avoid hardcoded registration.

⸻

Registry

Reuse existing registry infrastructure.

Avoid creating duplicate registries.

⸻

Knowledge

Maintain clear separation between:

* Collections
* Documents
* Chunks
* Embeddings
* Retrieval
* Citations

Avoid monolithic knowledge services.

⸻

Memory

Memory is not Knowledge.

Knowledge is persistent.

Memory is contextual.

Do not merge them.

⸻

Tool Runtime

Maintain execution flow:

Controller

↓

Permission

↓

Executor

↓

Tool

↓

Telemetry

Permission checks belong before execution.

⸻

Workflow

Workflow performs orchestration.

Workflow must not execute providers directly.

⸻

Events

Events should remain:

* immutable
* versionable
* traceable

⸻

SDK Rules

Everything exposed to other modules should remain stable.

Public SDK includes:

* Contracts
* DTOs
* Events
* Facades
* Manifest Schemas
* Service Providers

Internal implementation may change.

Avoid exposing internal classes.

⸻

Backwards Compatibility

Prefer additive changes.

Avoid breaking:

* APIs
* Contracts
* Manifests
* Events

Support legacy behaviour where practical.

⸻

Refactoring Rules

Prefer:

* composition
* contracts
* dependency injection
* metadata
* cohesive services

Avoid:

* large rewrites
* speculative abstractions
* duplicated logic
* unnecessary inheritance

⸻

Merge Cleanup Rules

TitanCore originated from multiple merged modules.

Continuously identify:

* namespace drift
* duplicate implementations
* duplicate services
* duplicate providers
* duplicate registries
* obsolete adapters
* compatibility layers
* dead code
* historical artifacts
* unused classes
* unused interfaces
* unused events
* obsolete configuration

Do not remove anything until evidence confirms it is safe.

⸻

Evidence Before Modification

Before deleting or consolidating code:

Verify:

* references
* container bindings
* manifests
* reflection
* events
* service providers
* dynamic resolution
* tests

Never assume unused code is safe to remove.

⸻

Validation Requirements

Whenever code changes:

Run:

* PHPUnit
* PHP lint
* Composer validation
* Static analysis (if available)
* Secret scanning
* CodeQL (if available)

Update tests when behaviour changes.

⸻

Reporting Format

When reporting findings include:

* Finding ID
* Severity
* Description
* Evidence
* Files affected
* Recommended action
* Validation performed

Do not provide vague recommendations.

⸻

Architectural Decision Authority

If a requested change conflicts with existing architecture:

Do not invent a solution.

Document the conflict.

Recommend alternatives.

Wait for approval before changing architecture.

⸻

Engineering Philosophy

Prefer:

* small commits
* deterministic behaviour
* explicit contracts
* loose coupling
* high cohesion
* platform neutrality
* maintainability
* readability
* simplicity

The goal is not merely to make TitanCore work.

The goal is to preserve TitanCore as the stable Platform Kernel for the entire Titan ecosystem.
