CHANGELOG
=========

0.13
----

 * Add a Cerebras assistant message normalizer that drops the `reasoning_content` of the shared contract, which Cerebras answers with a 400 `property 'messages.N.assistant.reasoning_content' is unsupported`; its streamed responses carry a reasoning trace, so a replayed turn reaches it

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Add a `baseUrl` argument to the model client and the factory to target Cerebras-compatible endpoints
 * Raise a `RuntimeException` on unhandled HTTP error statuses before streaming, instead of returning an empty stream

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

 * [BC BREAK] Streaming completion responses now yield typed deltas from the Generic completions converter (`TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, `TokenUsage`)

0.4
---

 * Add structured output support
 * Add tool call support

0.1
---

 * Add the bridge
