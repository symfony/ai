CHANGELOG
=========

0.10
----

 * Throw `IncompleteStreamException` when a stream ends before a finish reason
 * Throw `ModelNotFoundException` when a 404 response reports a missing model

0.9
---

 * Add rerank capabilities
 * Add text-to-speech capabilities
 * Update Static model list

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * Add support for `openrouter/free`

0.3
---

 * Add support for `openrouter/bodybuilder`

0.2
---

 * Add support for structured output via ModelApiCatalog

0.1
---

 * Add the bridge
