CHANGELOG
=========

0.8
---

 * [BC BREAK] `GeminiContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * [BC BREAK] `ResultConverter` now returns a `MultiPartResult` when there are multiple `parts` in a `candidate`
 * [BC BREAK] `ResultConverter` now `ExecutableCodeResult` and `CodeExecutionResult` parts when using `code_execution` server tool
 * [BC BREAK] Throwing when code execution server tool fails is replaced with `CodeExecutionResult::isSucceeded()`
 * Add possibility to pass `tool_config` to the model

0.7
---

 * [BC BREAK] Streaming responses now yield `TextDelta`, `BinaryDelta`, `ToolCallComplete`, and `ChoiceDelta` instead of result objects and raw strings

0.1
---

 * Add the bridge

0.5
---

 * Remove discontinued Gemini models:
   * `text-embedding-004`
   * `gemini-embedding-exp-03-07`
   * `gemini-1.5-flash`
   * `gemini-2.0-flash-thinking-exp-01-21`
   * `gemini-2.0-flash-lite-preview-02-05`
   * `gemini-2.0-pro-exp-02-05`
 * Renamed model according to Google documentation:
   * `embedding-001` to `gemini-embedding-001`
 * Add support to newly available models:
   * `gemini-2.5-flash-lite-preview-09-2025`
   * `gemini-2.5-flash-native-audio-preview-12-2025`
   * `gemini-3.1-pro-preview`
