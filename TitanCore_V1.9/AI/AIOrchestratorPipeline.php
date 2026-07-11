<?php

namespace Modules\TitanCore\AI;

use Modules\TitanCore\Contracts\AI\CitationContract;
use Modules\TitanCore\Contracts\AI\GuardrailContract;
use Modules\TitanCore\Contracts\AI\RetrievalContract;
use Modules\TitanCore\Contracts\AI\ToolExecutorContract;

/**
 * Manifest-backed AI orchestration pipeline.
 *
 * Runs incoming AI requests through the following ordered stages:
 *
 *   1. Guardrail check  — blocks disallowed inputs before anything else runs.
 *   2. Retrieval        — fetches relevant knowledge-base context.
 *   3. Tool execution   — resolves and calls any tool declared in the manifest.
 *   4. Citation         — resolves source references from retrieved documents.
 *
 * Any stage is optional: pass `null` for stages that are not wired up.
 * A guardrail hit short-circuits the pipeline and returns a blocked response
 * without executing subsequent stages.
 *
 * Manifest shape expected under the `tools` key:
 *   [ 'tool_name' => ['handler' => 'Class', 'input_schema' => [...]], ... ]
 */
class AIOrchestratorPipeline
{
    public function __construct(
        private readonly ?GuardrailContract     $guardrail,
        private readonly ?RetrievalContract     $retrieval,
        private readonly ?ToolExecutorContract  $toolExecutor,
        private readonly ?CitationContract      $citation,
        /** Module AI manifest: tool definitions + module metadata. */
        private readonly array                  $manifest = [],
    ) {}

    /**
     * Run the pipeline for the given request payload.
     *
     * @param  array  $request   Must contain at minimum:
     *                           - 'text'    : string — user input / query
     *                           - 'tool'    : string|null — declared tool to invoke (optional)
     *                           - 'params'  : array  — tool parameters (used when 'tool' is set)
     * @param  array  $context   Runtime context forwarded to every stage.
     * @return array             Normalised pipeline result:
     *                           ['ok'=>bool, 'blocked'=>bool, 'retrieval'=>array,
     *                            'tool_result'=>array|null, 'citations'=>array, 'stage'=>string]
     */
    public function run(array $request, array $context = []): array
    {
        $text   = (string) ($request['text'] ?? '');
        $tool   = $request['tool'] ?? null;
        $params = (array) ($request['params'] ?? []);

        // ── 1. Guardrail check ────────────────────────────────────────────────
        if ($this->guardrail !== null) {
            $guard = $this->guardrail->evaluate([
                'tool'    => $tool,
                'params'  => $params,
                'context' => $context,
                'text'    => $text,
            ]);

            if (!($guard['pass'] ?? true)) {
                return [
                    'ok'          => false,
                    'blocked'     => true,
                    'reason'      => $guard['reason'] ?? 'blocked by guardrail',
                    'retrieval'   => [],
                    'tool_result' => null,
                    'citations'   => [],
                    'stage'       => 'guardrail',
                ];
            }
        }

        // ── 2. Retrieval ──────────────────────────────────────────────────────
        $retrievedDocs = [];
        if ($this->retrieval !== null && $text !== '') {
            $retrievedDocs = $this->retrieval->retrieve($text, $context);
        }

        // ── 3. Tool execution ─────────────────────────────────────────────────
        $toolResult = null;
        if ($tool !== null && $this->toolExecutor !== null) {
            $toolResult = $this->toolExecutor->execute($tool, $params, $context)->toArray();
        }

        // ── 4. Citation resolution ────────────────────────────────────────────
        $citations = [];
        if ($this->citation !== null && !empty($retrievedDocs)) {
            $citations = $this->citation->resolve($text, $retrievedDocs);
        }

        return [
            'ok'          => true,
            'blocked'     => false,
            'retrieval'   => $retrievedDocs,
            'tool_result' => $toolResult,
            'citations'   => $citations,
            'stage'       => 'complete',
        ];
    }
}
