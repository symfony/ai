CHANGELOG
=========

0.14
----

 * Add `Realtime` model, `Realtime\ModelClient`, `Realtime\ResultConverter`, and catalog entries (`gpt-4o-realtime-preview`, `gpt-4o-mini-realtime-preview`) for `POST /v1/realtime/client_secrets`

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Throw typed exceptions on rate limit and server error events mid-stream
 * Raise a `RuntimeException` on unhandled HTTP error statuses before streaming, instead of returning an empty stream

0.10
----

 * Throw `ExceedContextSizeException` instead of `BadRequestException` when a 400 response reports a context overflow
 * Throw `IncompleteStreamException` when a Responses stream ends before `response.completed`
 * Replace malformed UTF-8 sequences in request bodies instead of aborting the request
 * Throw `ModelNotFoundException` when a 404 response reports a missing model

0.8
---

 * [BC BREAK] `OpenAiContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * HTTP 400/401/429 responses now throw dedicated exceptions (`BadRequestException`, `AuthenticationException`, `RateLimitExceededException`)

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
