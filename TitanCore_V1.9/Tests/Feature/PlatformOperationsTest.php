<?php

namespace Modules\TitanCore\Tests\Feature;

use Tests\TestCase;

/**
 * Feature tests for the Platform Operations endpoints added in issue #34.
 *
 * All routes require authentication + super-admin middleware.
 * These tests verify that unauthenticated requests are rejected and that
 * the routes are registered correctly.
 */
class PlatformOperationsTest extends TestCase
{
    // ── API Key Management ────────────────────────────────────────────────────

    /** @test */
    public function api_keys_index_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/api-keys');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function api_keys_store_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/api-keys', ['name' => 'Test Key']);
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function api_keys_show_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/api-keys/1');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function api_keys_update_requires_auth(): void
    {
        $response = $this->putJson('/api/v1/api-keys/1', ['name' => 'Updated Key']);
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function api_keys_revoke_requires_auth(): void
    {
        $response = $this->deleteJson('/api/v1/api-keys/1');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    // ── Logs ──────────────────────────────────────────────────────────────────

    /** @test */
    public function logs_index_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/logs');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function logs_app_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/logs/app');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function logs_platform_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/logs/platform');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    // ── Audit ─────────────────────────────────────────────────────────────────

    /** @test */
    public function audit_index_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/audit');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function audit_tools_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/audit/tools');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function audit_export_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/audit/export');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    // ── Maintenance ───────────────────────────────────────────────────────────

    /** @test */
    public function maintenance_index_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/maintenance');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function maintenance_enable_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/maintenance/enable');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }

    /** @test */
    public function maintenance_disable_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/maintenance/disable');
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }
}
