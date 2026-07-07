<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Modules\TitanCore\Services\Providers\TitanAiProvider;

/**
 * TitanCoreRouter
 *
 * Routes invocations to backend providers (Titan AI currently).
 *
 * Key resolution order (Titan AI):
 * 1) Tenant/company context key (titan_tenant_links where company_id = active company id)
 * 2) Fallback key (titan_tenant_links where company_id = 1)
 * 3) Config/env fallback (config('titancore.providers.titanai.api_key'))
 */
class TitanCoreRouter
{
    public function __construct()
    {
        // no eager DI; keep router safe to resolve anywhere
    }

    public function invokeTool(array $request): array
    {
        $providers = config('titancore.providers', []);
        $magic = Arr::get($providers, 'titanai', []);

        if (!Arr::get($magic, 'enabled', false)) {
            return ['ok' => false, 'status' => 503, 'body' => ['error' => 'Titan AI provider disabled']];
        }

        $baseUrl = (string) Arr::get($magic, 'base_url', '');
        $timeout = (int) Arr::get($magic, 'timeout_seconds', 60);

        if (!$baseUrl) {
            return ['ok' => false, 'status' => 422, 'body' => ['error' => 'Titan AI provider not configured (base_url)']];
        }

        $apiKey = $this->resolvetitanaiKey((string) Arr::get($magic, 'api_key', ''));

        if (!$apiKey) {
            return ['ok' => false, 'status' => 422, 'body' => ['error' => 'Titan AI provider not configured (no api key found in titan_tenant_links or config)']];
        }

        // Build client/provider per-call so the correct key is used (tenant-safe)
        $client = new TitanAiClient($baseUrl, $apiKey, $timeout);
        $provider = new TitanAiProvider($client);

        // Audit log (best-effort)
        $runId = null;
        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $runId = DB::table('ai_runs')->insertGetId([
                    'company_id' => $this->resolveCompanyIdFromContext() ?: 1,
                    'user_id' => Auth::check() ? Auth::id() : null,
                    'provider' => 'titanai',
                    'action' => (string) (\Illuminate\Support\Arr::get($request, 'tool') ?: 'proxy'),
                    'model' => null,
                    'input' => json_encode($request),
                    'status' => 'running',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {}

        $result = $provider->invoke($request, $magic);

        try {
            if ($runId && DB::getSchemaBuilder()->hasTable('ai_runs')) {
                DB::table('ai_runs')->where('id', $runId)->update([
                    'output' => json_encode($result),
                    'status' => ($result['ok'] ?? false) ? 'success' : 'error',
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {}

        return $result;
    }

    /**
     * Resolve active company id from common Worksuite contexts.
     */
    protected function resolveCompanyIdFromContext(): ?int
    {
        // 1) Worksuite helper (if available)
        try {
            if (function_exists('company')) {
                $c = company();
                if ($c && isset($c->id)) {
                    return (int) $c->id;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) Auth user company_id (common)
        try {
            if (Auth::check()) {
                $u = Auth::user();
                if ($u && isset($u->company_id) && $u->company_id) {
                    return (int) $u->company_id;
                }
                // some installs use companyId
                if ($u && isset($u->companyId) && $u->companyId) {
                    return (int) $u->companyId;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 3) Session
        try {
            $sid = Session::get('company_id');
            if ($sid) {
                return (int) $sid;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * Get Titan AI API key with tenant resolution + fallback.
     */
    protected function resolvetitanaiKey(string $configFallbackKey = ''): string
    {
        $companyId = $this->resolveCompanyIdFromContext();

        // First: active company key
        $key = $this->lookupKeyForCompany($companyId);

        // Second: fallback to company 1
        if (!$key) {
            $key = $this->lookupKeyForCompany(1);
        }

        // Third: config/env fallback
        if (!$key && $configFallbackKey) {
            $key = $configFallbackKey;
        }

        return (string) ($key ?: '');
    }

    protected function lookupKeyForCompany(?int $companyId): ?string
    {
        if (!$companyId) {
            return null;
        }

        try {
            // Use DB lookup; table created by TitanCore migration
            return DB::table('titan_tenant_links')
                ->where('provider', 'titanai')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->value('api_key');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
