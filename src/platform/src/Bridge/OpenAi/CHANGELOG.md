CHANGELOG
=========

0.7
---

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
