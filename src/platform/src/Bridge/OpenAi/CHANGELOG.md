CHANGELOG
=========

0.8
---

 * [BC BREAK] `OpenAiContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * Add token usage extraction for embeddings responses
 * Add `gpt-5.4-mini` and `gpt-5.4-nano` to `ModelCatalog`
 * [BC BREAK] GPT streaming responses now yield `TextDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas instead of raw strings and `ToolCallResult`
 * Add reasoning content streaming support via `ThinkingDelta`

0.3
---

 * Support token usage extraction for streamed responses

0.2
---

 * Support for Whisper verbose transcription

0.1
---

 * Add the bridge
