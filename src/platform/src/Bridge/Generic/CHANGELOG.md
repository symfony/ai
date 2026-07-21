CHANGELOG
=========

0.12
----

 * Throw a clear exception for malformed tool call arguments

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Throw typed exceptions on rate limit and server error events mid-stream
 * Normalize the `baseUrl` and tolerate a trailing slash in the completions and embeddings model clients
 * Raise a `RuntimeException` on unhandled HTTP error statuses before streaming, instead of returning an empty stream

0.10
----

 * Throw `ExceedContextSizeException` instead of `BadRequestException` when a 400 response reports a context overflow
 * Request usage stats for streamed responses by default when no `stream_options` provided

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
