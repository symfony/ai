CHANGELOG
=========

0.7
---

 * [BC BREAK] Streaming tool-call responses now yield `ToolCallComplete`; `OllamaMessageChunk` now implements `DeltaInterface`
 * Add support for `structured_output` capability in `OllamaApiCatalog`
 * Replace `ModelCatalog` by `OllamaApiCatalog`
 * Rename `OllamaApiCatalog` to `ModelCatalog`
 * [BC BREAK] `Ollama` model is now `final`
 * Add model information to token usage extraction

0.4
---

 * [BC BREAK] The `hostUrl` parameter for `OllamaClient` has been removed
 * [BC BREAK] The `host` parameter for `OllamaApiCatalog` has been removed
 * [BC BREAK] The `hostUrl` parameter for `PlatformFactory::create()` has been renamed `endpoint`

0.1
---

 * Add the bridge
