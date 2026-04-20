CHANGELOG
=========

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * [BC BREAK] Stream responses now yield `ThinkingDelta`, `ThinkingComplete`, `TextDelta`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas

0.4
---

 * [BC BREAK]: The stream generator now yields `ThinkingContent` objects in addition to strings and `ToolCallResult`
 * Parse reasoning_content in streaming responses
 * Add Capability::THINKING to deepseek-reasoner

0.1
---

 * Add the bridge
