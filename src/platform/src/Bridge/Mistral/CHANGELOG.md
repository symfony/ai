CHANGELOG
=========

0.12
----

 * Add Voxtral speech-to-text support through the dedicated `/v1/audio/transcriptions` endpoint, exposing a `SpeechToText` model, model client, result converter and audio normalizer (opt-in via `new SpeechToText('voxtral-mini-latest')`)

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Add a `baseUrl` argument to the model clients and the factory to target Mistral-compatible endpoints
 * Raise a `RuntimeException` on unhandled HTTP error statuses before streaming, instead of returning an empty stream
 * Add OCR support by `mistral-ocr-latest` model and `/v1/ocr` endpoint, returning a typed `OcrResult` with pages, layout images and annotations

0.10
----

 * Throw `ExceedContextSizeException` instead of `BadRequestException` when a 400 response reports a context overflow
 * Throw `IncompleteStreamException` when a stream ends before a finish reason
 * Throw `ModelNotFoundException` when a 404 response reports a missing model

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * HTTP 400/401/429 responses now throw dedicated exceptions (`BadRequestException`, `AuthenticationException`, `RateLimitExceededException`)

0.7
---

 * Add token usage extraction for embeddings
 * [BC BREAK] Streaming completion responses now yield typed deltas from the Generic completions converter (`TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, `TokenUsage`)

0.1
---

 * Add the bridge
