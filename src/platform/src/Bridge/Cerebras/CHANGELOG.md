CHANGELOG
=========

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * [BC BREAK] Streaming completion responses now yield typed deltas from the Generic completions converter (`TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, `TokenUsage`)

0.4
---

 * Add structured output support
 * Add tool call support

0.1
---

 * Add the bridge
