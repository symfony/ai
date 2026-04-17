CHANGELOG
=========

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * Add token usage extraction for embeddings responses
 * [BC BREAK] OpenAI-compatible completion streams now yield `TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas

0.4
---

 * Add support for token usage tracking

0.1
---

 * Add the bridge
