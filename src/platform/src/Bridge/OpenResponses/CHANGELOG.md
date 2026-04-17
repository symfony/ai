CHANGELOG
=========

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * [BC BREAK] Streaming responses now yield `TextDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas instead of raw strings and `ToolCallResult`
 * Add reasoning content streaming support via `ThinkingDelta`

0.4
---

 * Add the bridge
