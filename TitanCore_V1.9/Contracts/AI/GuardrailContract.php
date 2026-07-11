<?php

namespace Modules\TitanCore\Contracts\AI;

interface GuardrailContract
{
    /**
     * Evaluate whether the given input passes guardrail rules.
     *
     * @param  array  $input   Normalised payload: ['tool'=>string, 'params'=>array, 'context'=>array]
     * @return array           ['pass'=>bool, 'reason'=>string|null]
     */
    public function evaluate(array $input): array;
}
