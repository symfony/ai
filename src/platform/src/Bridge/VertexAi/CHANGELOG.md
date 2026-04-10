CHANGELOG
=========

0.8
---

 * [BC BREAK] `GeminiContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * Add support for Gemini 3.1 Flash Lite preview model (`gemini-3.1-flash-lite-preview`)
 * Add support for Gemini 3 Flash preview model (`gemini-3-flash-preview`)
 * [BC BREAK] `ResultConverter` now returns a `MultiPartResult` when there are multiple `parts` in a `candidate`
 * [BC BREAK] `ResultConverter` now returns `ExecutableCodeResult` and `CodeExecutionResult` parts when using `code_execution` server tool
 * [BC BREAK] Throwing when code execution server tool fails is replaced with `CodeExecutionResult::isSucceeded()`

0.7
---

 * Add token usage extraction for embeddings responses
 * [BC BREAK] Gemini streaming responses now yield `TextDelta`, `BinaryDelta`, `ToolCallComplete`, and `ChoiceDelta` instead of result objects and raw strings

0.6
---

 * Add support for global endpoint with API key authentication (no `location`/`project_id` required)

0.2
---

 * Add support for API key authentication

0.1
---

 * Add the bridge
