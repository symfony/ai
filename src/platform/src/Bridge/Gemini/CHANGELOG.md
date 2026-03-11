CHANGELOG
=========

0.8
---

 * [BC BREAK] `GeminiContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * [BC BREAK] `ResultConverter` now returns a `MultiPartResult` when there are multiple `parts` in a `candidate`
 * [BC BREAK] `ResultConverter` now `ExecutableCodeResult` and `CodeExecutionResult` parts when using `code_execution` server tool
 * [BC BREAK] Throwing when code execution server tool fails is replaced with `CodeExecutionResult::isSucceeded()`
 * [BC BREAK] Replace the static `ModelCatalog` with a dynamic implementation backed by the Gemini `models` REST API; capabilities and token limits are now discovered at runtime
 * [BC BREAK] Merge the dedicated `Embeddings/ModelClient` and `Embeddings/ResultConverter` into the unified `ModelClient` and `ResultConverter` at the bridge root
 * Add `endpoint` and `version` parameters to `Factory::createProvider()` / `Factory::createPlatform()` to allow targeting a custom Gemini API host
 * Add possibility to pass `tool_config` to the model
 * HTTP 400/401/429 responses now throw dedicated exceptions (`BadRequestException`, `AuthenticationException`, `RateLimitExceededException`)

0.7
---

 * [BC BREAK] Streaming responses now yield `TextDelta`, `BinaryDelta`, `ToolCallComplete`, and `ChoiceDelta` instead of result objects and raw strings

0.1
---

 * Add the bridge
