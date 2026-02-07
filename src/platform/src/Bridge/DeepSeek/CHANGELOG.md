CHANGELOG
=========

0.14
----

 * Add model information to token usage extraction

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Add a `baseUrl` argument to the model client and the factory to target DeepSeek-compatible endpoints
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

 * [BC BREAK] Stream responses now yield `ThinkingDelta`, `ThinkingComplete`, `TextDelta`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas

0.4
---

 * [BC BREAK]: The stream generator now yields `ThinkingContent` objects in addition to strings and `ToolCallResult`
 * Parse reasoning_content in streaming responses
 * Add Capability::THINKING to deepseek-reasoner

0.1
---

 * Add the bridge
