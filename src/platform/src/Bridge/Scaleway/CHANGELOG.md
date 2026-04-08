CHANGELOG
=========

0.7
---

 * Add token usage extraction for embeddings responses
 * [BC BREAK] Streaming completion responses now yield typed deltas from the Generic completions converter (`TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, `TokenUsage`)

0.1
---

 * Add the bridge
 * Add Responses API support via the Open Responses bridge
