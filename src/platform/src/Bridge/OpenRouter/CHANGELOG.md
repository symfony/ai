CHANGELOG
=========

0.13
----

 * Add configurable `$baseUrl` constructor parameter to `ModelApiCatalog`

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Add a `baseUrl` argument to the factory to target OpenRouter-compatible endpoints

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
 * Add support for video generation via the `POST /api/v1/videos` endpoint

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
