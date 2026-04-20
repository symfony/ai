CHANGELOG
=========

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
