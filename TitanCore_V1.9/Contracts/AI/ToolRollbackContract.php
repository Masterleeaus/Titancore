<?php

namespace Modules\TitanCore\Contracts\AI;

/**
 * Contract for tool handlers that support undo / rollback.
 *
 * Implement this interface alongside your `__invoke` handler when the tool
 * mutates state that can be reversed (e.g. creates a record that can be
 * deleted, sends a draft that can be cancelled).
 *
 * The rollback method receives the same $params that were passed to the
 * original execution, plus the $result data returned by the handler so that
 * the rollback logic can locate and reverse the specific change.
 *
 * Usage in the executor / orchestrator:
 *
 *   if ($handler instanceof ToolRollbackContract) {
 *       $handler->rollback($params, $result->data);
 *   }
 */
interface ToolRollbackContract
{
    /**
     * Undo the side-effects produced by a previous `__invoke` call.
     *
     * @param  array  $params  The original input parameters used during execution.
     * @param  array  $result  The data returned by the handler's `__invoke` call.
     *
     * @return void
     */
    public function rollback(array $params, array $result): void;
}
