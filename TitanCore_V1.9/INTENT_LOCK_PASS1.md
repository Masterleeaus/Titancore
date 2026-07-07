# INTENT LOCK — PASS 1

This module is part of the Titan AI stack.

## Hard Rules (enforced)
- This module MUST NOT call any AI provider directly (OpenAI/Anthropic/etc).
- All AI execution must be routed through **TitanZero** (the single gateway).
- This module may only produce **envelopes** (requests) and render **proposals** (responses) returned by TitanZero.
- Any attempt to call a provider directly should throw or be disabled.

## Notes
- Pass 1 focuses on lockdown and role clarity. Feature work comes in later passes.
