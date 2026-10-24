CHANGELOG
=========

0.16
----

 * Add a `$modelCatalog` argument to `Factory::createProvider()` and `Factory::createPlatform()`, e.g. to wrap the API-based catalog in a `CachedModelCatalog`

0.15
----

 * Add `UserMessageNormalizer` to send image content in Ollama's native message format
 * Add support for decision models (`decision` capability, Ollama >= 0.35.0), served through `POST /v1/systemone`

0.14
----

 * Add model information to token usage extraction

0.13
----

 * Add `ToolCallStart` deltas to streaming responses

0.10
----

 * Throw `IncompleteStreamException` when a stream ends before a `done` message

0.8
---

 * [BC BREAK] `OllamaContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * [BC BREAK] Streaming tool-call responses now yield `ToolCallComplete`; `OllamaMessageChunk` now implements `DeltaInterface`
 * Add support for `structured_output` capability in `OllamaApiCatalog`
 * Replace `ModelCatalog` by `OllamaApiCatalog`
 * Rename `OllamaApiCatalog` to `ModelCatalog`
 * [BC BREAK] `Ollama` model is now `final`

0.4
---

 * [BC BREAK] The `hostUrl` parameter for `OllamaClient` has been removed
 * [BC BREAK] The `host` parameter for `OllamaApiCatalog` has been removed
 * [BC BREAK] The `hostUrl` parameter for `PlatformFactory::create()` has been renamed `endpoint`

0.1
---

 * Add the bridge
