CHANGELOG
=========

0.7
---

 * [BC BREAK] Streaming responses now yield `TextDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas instead of raw strings and `ToolCallResult`
 * Add reasoning content streaming support via `ThinkingDelta`

0.4
---

 * Add the bridge
