CHANGELOG
=========

0.10
----

 * Throw `ExceedContextSizeException` instead of `BadRequestException` when a 400 response reports a context overflow
 * Throw `IncompleteStreamException` when a stream ends before `response.completed`
 * Throw a clear exception when a non-streaming response is incomplete or yields no content, instead of building an empty `MultiPartResult`
 * Replace malformed UTF-8 sequences in request bodies instead of aborting the request

0.8
---

 * [BC BREAK] `OpenResponsesContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * [BC BREAK] Streaming responses now yield `TextDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas instead of raw strings and `ToolCallResult`
 * Add reasoning content streaming support via `ThinkingDelta`

0.4
---

 * Add the bridge
