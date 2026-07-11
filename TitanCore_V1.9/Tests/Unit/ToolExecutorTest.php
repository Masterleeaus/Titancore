<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\AI\AIOrchestratorPipeline;
use Modules\TitanCore\AI\ToolExecutor;
use Modules\TitanCore\AI\ToolPermissionGate;
use Modules\TitanCore\AI\ValueObjects\ToolResult;
use Modules\TitanCore\Contracts\AI\CitationContract;
use Modules\TitanCore\Contracts\AI\GuardrailContract;
use Modules\TitanCore\Contracts\AI\RetrievalContract;
use Modules\TitanCore\Contracts\AI\ToolExecutorContract;
use Modules\TitanCore\Contracts\AI\ToolRollbackContract;
use Modules\TitanCore\Events\AiRequestCompleted;
use Modules\TitanCore\Exceptions\AI\ToolHandlerNotFoundException;
use Modules\TitanCore\Exceptions\AI\ToolInputValidationException;
use Modules\TitanCore\Exceptions\AI\ToolNotAllowedException;
use Modules\TitanCore\Exceptions\AI\ToolPermissionDeniedException;
use Modules\TitanCore\Exceptions\AI\ToolTimedOutException;
use PHPUnit\Framework\TestCase;

// ─── Inline handler stubs ─────────────────────────────────────────────────────

/** @internal Minimal valid handler used in test scenarios. */
class EchoToolHandler
{
    public function __invoke(array $params): array
    {
        return ['echo' => $params];
    }
}

/** @internal Recursive handler used to verify recursion protection. */
class RecursiveToolHandler
{
    public static ?ToolExecutor $executor = null;

    public function __invoke(array $params): array
    {
        return self::$executor->execute('recursive', $params);
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class ToolExecutorTest extends TestCase
{
    // ── Contracts interface smoke tests ──────────────────────────────────────

    public function test_tool_executor_contract_is_interface(): void
    {
        $this->assertTrue(interface_exists(ToolExecutorContract::class));
    }

    public function test_retrieval_contract_is_interface(): void
    {
        $this->assertTrue(interface_exists(RetrievalContract::class));
    }

    public function test_indexing_contract_is_interface(): void
    {
        $this->assertTrue(interface_exists(\Modules\TitanCore\Contracts\AI\IndexingContract::class));
    }

    public function test_guardrail_contract_is_interface(): void
    {
        $this->assertTrue(interface_exists(GuardrailContract::class));
    }

    public function test_citation_contract_is_interface(): void
    {
        $this->assertTrue(interface_exists(CitationContract::class));
    }

    // ── ToolResult value object ───────────────────────────────────────────────

    public function test_tool_result_to_array_contains_required_keys(): void
    {
        $result = new ToolResult(
            ok: true,
            tool: 'test.tool',
            data: ['key' => 'value'],
            message: 'ok',
        );

        $arr = $result->toArray();

        $this->assertArrayHasKey('ok', $arr);
        $this->assertArrayHasKey('tool', $arr);
        $this->assertArrayHasKey('data', $arr);
        $this->assertArrayHasKey('message', $arr);
        $this->assertArrayHasKey('warnings', $arr);
        $this->assertArrayHasKey('audit_ref', $arr);
        $this->assertTrue($arr['ok']);
        $this->assertSame('test.tool', $arr['tool']);
    }

    public function test_ai_request_completed_event_sets_timestamp_and_version(): void
    {
        $event = new AiRequestCompleted('corr-1', 'ok', 123, 4, 5, 1.25);

        $this->assertSame('corr-1', $event->correlationId);
        $this->assertSame('ok', $event->status);
        $this->assertSame('1.0.0', $event->eventVersion);
        $this->assertNotEmpty($event->occurredAt);
    }

    // ── ToolExecutor: successful tool call ────────────────────────────────────

    public function test_successful_tool_call_returns_tool_result(): void
    {
        $executor = new ToolExecutor([
            'echo' => [
                'handler' => EchoToolHandler::class,
                'input_schema' => ['message' => 'required|string'],
            ],
        ]);

        $result = $executor->execute('echo', ['message' => 'hello']);

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->ok);
        $this->assertSame('echo', $result->tool);
        $this->assertSame(['echo' => ['message' => 'hello']], $result->data);
    }

    // ── ToolExecutor: missing handler ─────────────────────────────────────────

    public function test_missing_handler_class_throws_tool_handler_not_found_exception(): void
    {
        $executor = new ToolExecutor([
            'ghost.tool' => [
                'handler' => 'Modules\TitanCore\Tools\NonExistentHandler',
            ],
        ]);

        $this->expectException(ToolHandlerNotFoundException::class);
        $this->expectExceptionMessageMatches('/ghost\.tool/');
        $this->expectExceptionMessageMatches('/NonExistentHandler/');

        $executor->execute('ghost.tool', []);
    }

    public function test_tool_not_in_manifest_throws_tool_handler_not_found_exception(): void
    {
        $executor = new ToolExecutor([]);

        $this->expectException(ToolHandlerNotFoundException::class);
        $executor->execute('unknown.tool', []);
    }

    // ── ToolExecutor: input validation failure ────────────────────────────────

    public function test_missing_required_field_throws_input_validation_exception(): void
    {
        $executor = new ToolExecutor([
            'echo' => [
                'handler'      => EchoToolHandler::class,
                'input_schema' => ['message' => 'required|string'],
            ],
        ]);

        $this->expectException(ToolInputValidationException::class);
        $this->expectExceptionMessageMatches('/message/');

        $executor->execute('echo', []); // 'message' param missing
    }

    public function test_empty_required_field_throws_input_validation_exception(): void
    {
        $executor = new ToolExecutor([
            'echo' => [
                'handler'      => EchoToolHandler::class,
                'input_schema' => ['message' => 'required'],
            ],
        ]);

        $this->expectException(ToolInputValidationException::class);

        $executor->execute('echo', ['message' => '']);
    }

    public function test_validation_exception_carries_field_errors(): void
    {
        $executor = new ToolExecutor([
            'echo' => [
                'handler'      => EchoToolHandler::class,
                'input_schema' => ['name' => 'required', 'value' => 'required'],
            ],
        ]);

        try {
            $executor->execute('echo', []);
            $this->fail('Expected ToolInputValidationException was not thrown.');
        } catch (ToolInputValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
            $this->assertArrayHasKey('value', $e->errors);
        }
    }

    // ── AIOrchestratorPipeline ────────────────────────────────────────────────

    public function test_pipeline_runs_to_completion_with_all_null_stages(): void
    {
        $pipeline = new AIOrchestratorPipeline(null, null, null, null);

        $result = $pipeline->run(['text' => 'hello']);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['blocked']);
        $this->assertSame('complete', $result['stage']);
        $this->assertNull($result['tool_result']);
    }

    public function test_pipeline_guardrail_hit_short_circuits(): void
    {
        $guardrail = new class implements GuardrailContract {
            public function evaluate(array $input): array
            {
                return ['pass' => false, 'reason' => 'blocked content'];
            }
        };

        $pipeline = new AIOrchestratorPipeline($guardrail, null, null, null);

        $result = $pipeline->run(['text' => 'bad input']);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['blocked']);
        $this->assertSame('guardrail', $result['stage']);
        $this->assertSame('blocked content', $result['reason']);
    }

    public function test_pipeline_invokes_retrieval_and_returns_docs(): void
    {
        $retrieval = new class implements RetrievalContract {
            public function retrieve(string $query, array $context = [], int $maxResults = 5): array
            {
                return [['content' => 'doc1', 'score' => 0.9, 'source' => 'kb']];
            }
        };

        $pipeline = new AIOrchestratorPipeline(null, $retrieval, null, null);

        $result = $pipeline->run(['text' => 'query']);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['retrieval']);
        $this->assertSame('doc1', $result['retrieval'][0]['content']);
    }

    public function test_pipeline_executes_tool_and_returns_result(): void
    {
        $executor = new ToolExecutor([
            'echo' => ['handler' => EchoToolHandler::class],
        ]);

        $pipeline = new AIOrchestratorPipeline(null, null, $executor, null);

        $result = $pipeline->run(['text' => 'run tool', 'tool' => 'echo', 'params' => ['x' => 1]]);

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['tool_result']);
        $this->assertTrue($result['tool_result']['ok']);
        $this->assertSame('echo', $result['tool_result']['tool']);
    }

    public function test_pipeline_resolves_citations_from_retrieved_docs(): void
    {
        $retrieval = new class implements RetrievalContract {
            public function retrieve(string $query, array $context = [], int $maxResults = 5): array
            {
                return [['content' => 'snippet', 'score' => 0.8, 'source' => 'doc.pdf']];
            }
        };

        $citation = new class implements CitationContract {
            public function resolve(string $responseText, array $retrievedDocs): array
            {
                return array_map(fn($d) => ['ref' => '[1]', 'source' => $d['source'], 'excerpt' => $d['content']], $retrievedDocs);
            }
        };

        $pipeline = new AIOrchestratorPipeline(null, $retrieval, null, $citation);

        $result = $pipeline->run(['text' => 'query about something']);

        $this->assertCount(1, $result['citations']);
        $this->assertSame('doc.pdf', $result['citations'][0]['source']);
    }

    public function test_pipeline_passing_guardrail_continues_to_completion(): void
    {
        $guardrail = new class implements GuardrailContract {
            public function evaluate(array $input): array
            {
                return ['pass' => true];
            }
        };

        $pipeline = new AIOrchestratorPipeline($guardrail, null, null, null);

        $result = $pipeline->run(['text' => 'safe input']);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['blocked']);
        $this->assertSame('complete', $result['stage']);
    }

    // ── ToolExecutor: allowlist enforcement ───────────────────────────────────

    public function test_tool_not_in_allowlist_throws_tool_not_allowed_exception(): void
    {
        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            allowedTools: ['other.tool'],
        );

        $this->expectException(ToolNotAllowedException::class);
        $this->expectExceptionMessageMatches('/echo/');

        $executor->execute('echo', []);
    }

    public function test_allowlist_wildcard_permits_any_registered_tool(): void
    {
        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            allowedTools: '*',
        );

        $result = $executor->execute('echo', ['x' => 1]);

        $this->assertTrue($result->ok);
    }

    public function test_allowlist_array_permits_listed_tools(): void
    {
        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            allowedTools: ['echo'],
        );

        $result = $executor->execute('echo', []);

        $this->assertTrue($result->ok);
    }

    public function test_tool_not_allowed_exception_has_403_status_code(): void
    {
        $e = new ToolNotAllowedException('secret.tool');
        $this->assertSame(403, $e->getStatusCode());
    }

    // ── ToolExecutor: permission gate ─────────────────────────────────────────

    public function test_permission_denied_throws_tool_permission_denied_exception(): void
    {
        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            permissionChecker: fn(string $tool, array $ctx): bool => false,
        );

        $this->expectException(ToolPermissionDeniedException::class);
        $this->expectExceptionMessageMatches('/echo/');

        $executor->execute('echo', []);
    }

    public function test_permission_granted_allows_execution(): void
    {
        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            permissionChecker: fn(string $tool, array $ctx): bool => true,
        );

        $result = $executor->execute('echo', ['x' => 1]);

        $this->assertTrue($result->ok);
    }

    public function test_permission_denied_exception_has_403_status_code(): void
    {
        $e = new ToolPermissionDeniedException('admin.tool');
        $this->assertSame(403, $e->getStatusCode());
    }

    // ── ToolPermissionGate ────────────────────────────────────────────────────

    public function test_permission_gate_allows_tool_with_no_requirement(): void
    {
        $gate = new ToolPermissionGate([]);
        $this->assertTrue($gate->allows('any.tool', []));
    }

    public function test_permission_gate_denies_when_no_user_in_context(): void
    {
        $gate = new ToolPermissionGate(['echo' => 'use_ai_features']);
        $this->assertFalse($gate->allows('echo', []));
    }

    public function test_permission_gate_uses_can_method_on_user(): void
    {
        $user = new class {
            public function can(string $ability): bool
            {
                return $ability === 'use_ai_features';
            }
        };

        $gate = new ToolPermissionGate(['echo' => 'use_ai_features']);
        $this->assertTrue($gate->allows('echo', ['user' => $user]));
    }

    public function test_permission_gate_denies_when_user_lacks_permission(): void
    {
        $user = new class {
            public function can(string $ability): bool
            {
                return false;
            }
        };

        $gate = new ToolPermissionGate(['echo' => 'use_ai_features']);
        $this->assertFalse($gate->allows('echo', ['user' => $user]));
    }

    public function test_permission_gate_is_invokable(): void
    {
        $gate = new ToolPermissionGate([]);
        $this->assertTrue($gate('any.tool', []));
    }

    public function test_permission_gate_prefers_has_permission_to_over_can(): void
    {
        $user = new class {
            public array $calls = [];

            public function hasPermissionTo(string $perm): bool
            {
                $this->calls[] = 'hasPermissionTo';
                return true;
            }

            public function can(string $perm): bool
            {
                $this->calls[] = 'can';
                return false;
            }
        };

        $gate = new ToolPermissionGate(['echo' => 'use_ai_features']);
        $result = $gate->allows('echo', ['user' => $user]);

        $this->assertTrue($result);
        $this->assertContains('hasPermissionTo', $user->calls);
        $this->assertNotContains('can', $user->calls);
    }

    // ── ToolExecutor: dry-run mode ────────────────────────────────────────────

    public function test_dry_run_returns_result_without_invoking_handler(): void
    {
        $handlerCalled = false;

        $executor = new ToolExecutor(
            manifest: [
                'echo' => ['handler' => EchoToolHandler::class],
            ],
        );

        $result = $executor->execute('echo', ['x' => 99], ['dry_run' => true]);

        $this->assertTrue($result->ok);
        $this->assertSame('echo', $result->tool);
        $this->assertTrue($result->data['dry_run']);
        $this->assertSame(['x' => 99], $result->data['params']);
        $this->assertStringContainsString('dry-run', $result->message);
    }

    // ── ToolExecutor: audit writer ────────────────────────────────────────────

    public function test_audit_writer_called_on_successful_execution(): void
    {
        $auditEntries = [];

        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            auditWriter: function (array $entry) use (&$auditEntries): void {
                $auditEntries[] = $entry;
            },
        );

        $executor->execute('echo', ['msg' => 'hi'], ['user_id' => 42, 'company_id' => 7]);

        $this->assertCount(1, $auditEntries);
        $this->assertSame('echo', $auditEntries[0]['tool']);
        $this->assertSame(42, $auditEntries[0]['user_id']);
        $this->assertSame(7, $auditEntries[0]['company_id']);
        $this->assertSame('success', $auditEntries[0]['status']);
        $this->assertNotNull($auditEntries[0]['input_hash']);
        $this->assertIsInt($auditEntries[0]['duration_ms']);
        $this->assertSame('1.0.0', $auditEntries[0]['version']);
        $this->assertArrayHasKey('timestamp', $auditEntries[0]);
    }

    public function test_audit_writer_called_with_blocked_status_on_allowlist_violation(): void
    {
        $auditEntries = [];

        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            allowedTools: ['other.tool'],
            auditWriter: function (array $entry) use (&$auditEntries): void {
                $auditEntries[] = $entry;
            },
        );

        try {
            $executor->execute('echo', []);
        } catch (ToolNotAllowedException) {
        }

        $this->assertCount(1, $auditEntries);
        $this->assertSame('blocked', $auditEntries[0]['status']);
    }

    public function test_audit_writer_called_with_blocked_status_on_permission_denied(): void
    {
        $auditEntries = [];

        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            permissionChecker: fn() => false,
            auditWriter: function (array $entry) use (&$auditEntries): void {
                $auditEntries[] = $entry;
            },
        );

        try {
            $executor->execute('echo', []);
        } catch (ToolPermissionDeniedException) {
        }

        $this->assertCount(1, $auditEntries);
        $this->assertSame('blocked', $auditEntries[0]['status']);
    }

    public function test_audit_writer_called_with_failed_status_on_handler_not_found(): void
    {
        $auditEntries = [];

        $executor = new ToolExecutor(
            manifest: ['ghost' => ['handler' => 'NonExistentClass']],
            auditWriter: function (array $entry) use (&$auditEntries): void {
                $auditEntries[] = $entry;
            },
        );

        try {
            $executor->execute('ghost', []);
        } catch (ToolHandlerNotFoundException) {
        }

        $this->assertCount(1, $auditEntries);
        $this->assertSame('failed', $auditEntries[0]['status']);
    }

    public function test_audit_writer_called_with_dry_run_status(): void
    {
        $auditEntries = [];

        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            auditWriter: function (array $entry) use (&$auditEntries): void {
                $auditEntries[] = $entry;
            },
        );

        $executor->execute('echo', [], ['dry_run' => true]);

        $this->assertCount(1, $auditEntries);
        $this->assertSame('dry_run', $auditEntries[0]['status']);
    }

    public function test_audit_input_hash_is_sha256_of_json_params(): void
    {
        $auditEntries = [];
        $params = ['message' => 'hello'];

        $executor = new ToolExecutor(
            manifest: ['echo' => ['handler' => EchoToolHandler::class]],
            auditWriter: function (array $entry) use (&$auditEntries): void {
                $auditEntries[] = $entry;
            },
        );

        $executor->execute('echo', $params);

        $expectedHash = hash('sha256', (string) json_encode($params));
        $this->assertSame($expectedHash, $auditEntries[0]['input_hash']);
    }

    public function test_recursive_tool_execution_is_blocked(): void
    {
        $executor = new ToolExecutor([
            'recursive' => ['handler' => RecursiveToolHandler::class],
        ]);

        RecursiveToolHandler::$executor = $executor;

        $this->expectException(\Modules\TitanCore\Exceptions\AI\ToolRecursionDetectedException::class);

        $executor->execute('recursive', []);
    }

    // ── ToolTimedOutException ─────────────────────────────────────────────────

    public function test_timed_out_exception_message_contains_tool_name_and_timeout(): void
    {
        $e = new ToolTimedOutException('slow.tool', 30);
        $this->assertStringContainsString('slow.tool', $e->getMessage());
        $this->assertStringContainsString('30', $e->getMessage());
    }

    // ── ToolRollbackContract ──────────────────────────────────────────────────

    public function test_tool_rollback_contract_is_interface(): void
    {
        $this->assertTrue(interface_exists(ToolRollbackContract::class));
    }

    public function test_handler_can_implement_rollback_contract(): void
    {
        $handler = new class implements ToolRollbackContract {
            public array $rolledBack = [];

            public function __invoke(array $params): array
            {
                return ['record_id' => 42];
            }

            public function rollback(array $params, array $result): void
            {
                $this->rolledBack = ['params' => $params, 'result' => $result];
            }
        };

        $result = $handler([]);
        $this->assertSame(['record_id' => 42], $result);
        $this->assertTrue($handler instanceof ToolRollbackContract);

        $handler->rollback(['x' => 1], ['record_id' => 42]);
        $this->assertSame(['x' => 1], $handler->rolledBack['params']);
        $this->assertSame(['record_id' => 42], $handler->rolledBack['result']);
    }
}
