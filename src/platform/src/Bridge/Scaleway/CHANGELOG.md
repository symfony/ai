CHANGELOG
=========

0.14
----

 * Add model information to token usage extraction

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Add a `baseUrl` argument to the model clients and the factory to target Scaleway-compatible endpoints
 * Raise a `RuntimeException` on unhandled HTTP error statuses before streaming, instead of returning an empty stream

0.10
----

 * Throw `ExceedContextSizeException` when a 400 response reports a context overflow
 * Throw `IncompleteStreamException` when a stream ends before a finish reason
 * Throw a clear exception when a non-streaming response is incomplete or yields no content, instead of building an empty `MultiPartResult`

0.9
---

 * Add Responses API support via the Open Responses bridge

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * Add token usage extraction for embeddings responses
 * [BC BREAK] Streaming completion responses now yield typed deltas from the Generic completions converter (`TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, `TokenUsage`)

0.1
---

 * Add the bridge
