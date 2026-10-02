<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\ApiKeyManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ApiKeyManager service (Phase 5 — API Key Management).
 */
class ApiKeyManagerTest extends TestCase
{
    // ── add ───────────────────────────────────────────────────────────────────

    public function test_add_returns_an_id(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-test-1234', 'label' => 'Test']);

        $this->assertNotEmpty($id);
        $this->assertStringStartsWith('key_', $id);
    }

    public function test_add_sets_status_active(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-test']);

        $this->assertSame('active', $manager->status($id));
    }

    // ── summary / masking ─────────────────────────────────────────────────────

    public function test_summary_masks_key(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-test-longkey']);

        $summary = $manager->summary($id);

        $this->assertNotNull($summary);
        $this->assertStringStartsWith('sk-t', $summary['key']);
        $this->assertStringContainsString('*', $summary['key']);
        $this->assertStringNotContainsString('longkey', $summary['key']);
    }

    public function test_summary_returns_null_for_unknown_id(): void
    {
        $manager = new ApiKeyManager();

        $this->assertNull($manager->summary('nonexistent'));
    }

    public function test_all_masks_keys(): void
    {
        $manager = new ApiKeyManager();
        $manager->add(['provider' => 'openai', 'key' => 'sk-abcdefgh']);
        $manager->add(['provider' => 'anthropic', 'key' => 'ant-xyz123']);

        foreach ($manager->all() as $record) {
            $this->assertStringContainsString('*', $record['key']);
        }
    }

    // ── resolve ───────────────────────────────────────────────────────────────

    public function test_resolve_returns_plaintext_key(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-plaintext']);

        $this->assertSame('sk-plaintext', $manager->resolve($id));
    }

    public function test_resolve_returns_null_for_unknown_id(): void
    {
        $manager = new ApiKeyManager();

        $this->assertNull($manager->resolve('nonexistent'));
    }

    // ── disable / enable ──────────────────────────────────────────────────────

    public function test_disable_sets_status_disabled(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-test']);
        $result  = $manager->disable($id);

        $this->assertTrue($result);
        $this->assertSame('disabled', $manager->status($id));
    }

    public function test_enable_restores_status_active(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-test']);
        $manager->disable($id);
        $manager->enable($id);

        $this->assertSame('active', $manager->status($id));
    }

    public function test_disable_returns_false_for_unknown_id(): void
    {
        $manager = new ApiKeyManager();

        $this->assertFalse($manager->disable('nonexistent'));
    }

    // ── rotate ────────────────────────────────────────────────────────────────

    public function test_rotate_updates_key_and_sets_rotated_at(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-old']);
        $result  = $manager->rotate($id, 'sk-new');

        $this->assertTrue($result);
        $this->assertSame('sk-new', $manager->resolve($id));

        $summary = $manager->summary($id);
        $this->assertNotNull($summary['rotated_at']);
        $this->assertSame('active', $summary['status']);
    }

    public function test_rotate_returns_false_for_unknown_id(): void
    {
        $manager = new ApiKeyManager();

        $this->assertFalse($manager->rotate('nonexistent', 'sk-new'));
    }

    // ── remove ────────────────────────────────────────────────────────────────

    public function test_remove_deletes_credential(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-test']);
        $result  = $manager->remove($id);

        $this->assertTrue($result);
        $this->assertNull($manager->status($id));
        $this->assertNull($manager->summary($id));
    }

    public function test_remove_returns_false_for_unknown_id(): void
    {
        $manager = new ApiKeyManager();

        $this->assertFalse($manager->remove('nonexistent'));
    }

    // ── validate ──────────────────────────────────────────────────────────────

    public function test_validate_passes_for_complete_credential(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-valid']);
        $result  = $manager->validate($id);

        $this->assertTrue($result['ok']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_fails_for_missing_key(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => '']);
        $result  = $manager->validate($id);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_fails_for_unknown_id(): void
    {
        $manager = new ApiKeyManager();
        $result  = $manager->validate('nonexistent');

        $this->assertFalse($result['ok']);
    }

    // ── summaries for provider ────────────────────────────────────────────────

    public function test_summaries_for_provider_filters_correctly(): void
    {
        $manager = new ApiKeyManager();
        $manager->add(['provider' => 'openai',    'key' => 'sk-a']);
        $manager->add(['provider' => 'openai',    'key' => 'sk-b']);
        $manager->add(['provider' => 'anthropic', 'key' => 'ant-c']);

        $openai = $manager->summariesForProvider('openai');

        $this->assertCount(2, $openai);
        foreach ($openai as $record) {
            $this->assertSame('openai', $record['provider']);
        }
    }

    // ── update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_label(): void
    {
        $manager = new ApiKeyManager();
        $id      = $manager->add(['provider' => 'openai', 'key' => 'sk-test', 'label' => 'Old']);
        $manager->update($id, ['label' => 'New']);

        $summary = $manager->summary($id);
        $this->assertSame('New', $summary['label']);
    }

    public function test_update_returns_false_for_unknown_id(): void
    {
        $manager = new ApiKeyManager();

        $this->assertFalse($manager->update('nonexistent', ['label' => 'X']));
    }
}
