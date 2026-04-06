CHANGELOG
=========

0.7
---

 * Add token usage extraction for embeddings responses
 * [BC BREAK] OpenAI-compatible completion streams now yield `TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas
 * Add model information to token usage extraction

0.4
---

 * Add support for token usage tracking

0.1
---

 * Add the bridge
