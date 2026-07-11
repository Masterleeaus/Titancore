# META_PROMPT.md (TitanCore v0.3.1)

Role: Senior AI Integration Engineer
Objective: Harden AI config, wire provider adapters, add Health Dashboard, ensure idempotent seeders and OpenAPI docs.

Steps:
1. Parse `SCAN_REPORT/*.json` and target gaps.
2. Ensure `config/ai.php` reads env keys, no hardcoding.
3. Add `artisan ai:smoke` and health checks.
4. Generate/update `Docs/openapi.yaml` with endpoints.
5. Write tests for AI settings, quotas, and RBAC.
