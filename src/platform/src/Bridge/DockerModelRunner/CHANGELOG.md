CHANGELOG
=========

0.14
----

 * Add model information to token usage extraction

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Normalize the base URL and tolerate a trailing slash
 * [BC BREAK] Rename the `$hostUrl` constructor and factory argument to `$baseUrl`
 * Raise a `RuntimeException` on unhandled HTTP error statuses before streaming, instead of returning an empty stream

0.10
----

 * Throw `IncompleteStreamException` when a stream ends before a finish reason

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
