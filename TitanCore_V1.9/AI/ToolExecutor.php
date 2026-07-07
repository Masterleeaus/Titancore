<?php

namespace Modules\TitanCore\AI;

use Modules\TitanCore\AI\ValueObjects\ToolResult;
use Modules\TitanCore\Contracts\AI\ToolExecutorContract;
use Modules\TitanCore\Exceptions\AI\ToolHandlerNotFoundException;
use Modules\TitanCore\Exceptions\AI\ToolInputValidationException;
use Modules\TitanCore\Exceptions\AI\ToolNotAllowedException;
use Modules\TitanCore\Exceptions\AI\ToolPermissionDeniedException;
use Modules\TitanCore\Exceptions\AI\ToolTimedOutException;

/**
 * Declared tool executor with production-grade security controls.
 *
 * Resolves the handler class declared in a tool manifest, runs the full
 * security pipeline, and wraps the result in a consistent {@see ToolResult}
 * value object.
 *
 * Security pipeline (in order):
 *   1. Allowlist check   — rejects tools absent from `$allowedTools` (403).
 *   2. Manifest lookup   — rejects unknown/misconfigured handler classes.
 *   3. Permission gate   — optional callable; rejects on denied access (403).
 *   4. Input validation  — validates params against the declared input schema.
 *   5. Dry-run short-circuit — returns a no-side-effect response when requested.
 *   6. Timeout-guarded execution — hard-kills (SIGALRM when available) and soft
 *      time-checks terminate runs that exceed `$timeoutSeconds`.
 *   7. Audit write       — records every attempt (pass or fail) via `$auditWriter`.
 *
 * The manifest registry is a plain array of tool definitions keyed by tool name:
 *
 *   [
 *     'handler'      => 'Fully\Qualified\HandlerClass',   // required
 *     'input_schema' => [                                 // optional
 *       'field_name' => 'required|string',
 *       ...
 *     ],
 *   ]
 *
 * Handler classes must expose a public `__invoke(array $params): array` method
 * that returns a normalised result array.
 *
 * Context keys consumed by the executor:
 *   - 'user'       : mixed  — user model passed to the permission checker.
 *   - 'user_id'    : int    — stored in the audit log.
 *   - 'company_id' : int    — stored in the audit log (tenant boundary).
 *   - 'dry_run'    : bool   — when true, no handler is invoked.
 */
class ToolExecutor implements ToolExecutorContract
{
    /**
     * @param  array<string, array>        $manifest           Tool definitions keyed by tool name.
     * @param  string|array<int, string>   $allowedTools       '*' permits all registered tools;
     *                                                         an array restricts to listed slugs.
     * @param  int                         $timeoutSeconds     Maximum handler wall-clock time.
     * @param  callable|null               $auditWriter        fn(array $entry): void — receives
     *                                                         every execution attempt for logging.
     * @param  callable|null               $permissionChecker  fn(string $tool, array $ctx): bool
     */
    public function __construct(
        private readonly array            $manifest           = [],
        private readonly string|array     $allowedTools       = '*',
        private readonly int              $timeoutSeconds     = 30,
        private readonly mixed            $auditWriter        = null,
        private readonly mixed            $permissionChecker  = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute(string $toolName, array $params, array $context = []): ToolResult
    {
        $startTime = microtime(true);
        $userId    = $context['user_id']    ?? null;
        $companyId = $context['company_id'] ?? null;
        $dryRun    = (bool) ($context['dry_run'] ?? false);

        // ── 1. Allowlist check ────────────────────────────────────────────────
        if (!$this->isToolAllowed($toolName)) {
            $this->writeAudit($toolName, $userId, $companyId, $params, 'blocked', 0, 'Tool not in allowlist');
            throw new ToolNotAllowedException($toolName);
        }

        // ── 2. Manifest / handler resolution ─────────────────────────────────
        $definition   = $this->manifest[$toolName] ?? null;
        $handlerClass = $definition['handler'] ?? null;

        if ($handlerClass === null || !class_exists($handlerClass)) {
            $this->writeAudit($toolName, $userId, $companyId, $params, 'failed', 0, 'Handler not found');
            throw new ToolHandlerNotFoundException($toolName, $handlerClass ?? '(not declared)');
        }

        // ── 3. Permission gate ────────────────────────────────────────────────
        if ($this->permissionChecker !== null) {
            $permitted = (bool) ($this->permissionChecker)($toolName, $context);
            if (!$permitted) {
                $this->writeAudit($toolName, $userId, $companyId, $params, 'blocked', 0, 'Permission denied');
                throw new ToolPermissionDeniedException($toolName);
            }
        }

        // ── 4. Input validation ───────────────────────────────────────────────
        $schema = $definition['input_schema'] ?? [];
        $this->validateInput($toolName, $params, $schema);

        // ── 5. Dry-run short-circuit ──────────────────────────────────────────
        if ($dryRun) {
            $duration = $this->elapsedMs($startTime);
            $this->writeAudit($toolName, $userId, $companyId, $params, 'dry_run', $duration);
            return new ToolResult(
                ok: true,
                tool: $toolName,
                data: ['dry_run' => true, 'params' => $params],
                message: 'dry-run: no side-effects applied',
            );
        }

        // ── 6. Timeout-guarded execution ──────────────────────────────────────
        $timeoutMs = $this->timeoutMs();
        $alarmSet  = $this->armAlarm();

        try {
            $handler = new $handlerClass();
            $raw     = $handler($params);

            $duration = $this->elapsedMs($startTime);

            // Dispatch any pending signals (makes pcntl flag visible on cooperative runtimes).
            if ($alarmSet && function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Soft timeout check — catches cases where pcntl is unavailable or the
            // handler completed just as the alarm fired.
            if ($duration > $timeoutMs) {
                $this->writeAudit($toolName, $userId, $companyId, $params, 'timed_out', $duration);
                throw new ToolTimedOutException($toolName, $this->timeoutSeconds);
            }

            if ($alarmSet) {
                pcntl_alarm(0); // cancel outstanding alarm on success
            }

            // ── 7. Audit ──────────────────────────────────────────────────────
            $this->writeAudit($toolName, $userId, $companyId, $params, 'success', $duration);

            return new ToolResult(
                ok: true,
                tool: $toolName,
                data: is_array($raw) ? $raw : ['result' => $raw],
                message: 'ok',
            );
        } catch (ToolTimedOutException $e) {
            if ($alarmSet) {
                pcntl_alarm(0);
            }
            $duration = $this->elapsedMs($startTime);
            $this->writeAudit($toolName, $userId, $companyId, $params, 'timed_out', $duration, $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            if ($alarmSet) {
                pcntl_alarm(0);
            }
            $duration = $this->elapsedMs($startTime);
            $this->writeAudit($toolName, $userId, $companyId, $params, 'failed', $duration, $e->getMessage());
            throw $e;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Determine whether the given tool slug is permitted by the allowlist.
     *
     * When `$allowedTools` is `'*'` every registered tool is permitted.
     * When it is an array, only listed slugs are allowed.
     */
    private function isToolAllowed(string $toolName): bool
    {
        if ($this->allowedTools === '*') {
            return true;
        }

        return in_array($toolName, (array) $this->allowedTools, true);
    }

    /**
     * Return the configured timeout expressed in milliseconds.
     * Extracted to avoid repeating the `* 1000` conversion in multiple places.
     */
    private function timeoutMs(): int
    {
        return $this->timeoutSeconds * 1000;
    }

    /**
     * Arm a SIGALRM via the pcntl extension (Unix CLI / queue-worker only).
     *
     * A flag (`$this->alarmFired`) is set inside the signal handler instead of
     * throwing directly.  Throwing from a signal handler is unreliable in PHP
     * because signal delivery happens asynchronously and the exception may not
     * propagate correctly in all SAPI contexts.  Callers must invoke
     * `pcntl_signal_dispatch()` after the handler returns to process any
     * pending signal and then perform the soft elapsed-time check.
     *
     * Returns true when the alarm was successfully armed, false otherwise
     * (e.g. pcntl extension not loaded, or running under FPM/CGI).
     */
    private function armAlarm(): bool
    {
        if (!function_exists('pcntl_alarm') || !function_exists('pcntl_signal')) {
            return false;
        }

        pcntl_signal(SIGALRM, function (): void {
            // Signal received — the soft elapsed-time check that follows
            // pcntl_signal_dispatch() will detect the overrun and throw.
        });
        pcntl_alarm($this->timeoutSeconds);

        return true;
    }

    /**
     * Return elapsed milliseconds since $startTime (from microtime(true)).
     */
    private function elapsedMs(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }

    /**
     * Dispatch an audit entry to the injected writer (if any).
     */
    private function writeAudit(
        string  $toolName,
        mixed   $userId,
        mixed   $companyId,
        array   $params,
        string  $status,
        int     $durationMs,
        ?string $error = null,
    ): void {
        if ($this->auditWriter === null) {
            return;
        }

        ($this->auditWriter)([
            'tool'        => $toolName,
            'user_id'     => $userId,
            'company_id'  => $companyId,
            'input_hash'  => hash('sha256', (string) json_encode($params)),
            'status'      => $status,
            'duration_ms' => $durationMs,
            'error'       => $error,
        ]);
    }

    /**
     * Validate $params against a simple required-field schema.
     *
     * Schema format: ['field' => 'required|string', ...]
     * The `required` rule treats a missing key, a null value, or an empty
     * string as an invalid input — all three forms fail validation.
     * Additional rules (type checks, min/max) can be added here without
     * breaking existing handlers.
     *
     * @param  array<string, string>  $schema
     *
     * @throws ToolInputValidationException
     */
    private function validateInput(string $toolName, array $params, array $schema): void
    {
        $errors = [];

        foreach ($schema as $field => $rules) {
            $ruleList = array_map('trim', explode('|', $rules));

            if (in_array('required', $ruleList, true)) {
                if (!array_key_exists($field, $params) || $params[$field] === null || $params[$field] === '') {
                    $errors[$field] = 'This field is required.';
                }
            }
        }

        if (!empty($errors)) {
            throw new ToolInputValidationException($toolName, $errors);
        }
    }
}
