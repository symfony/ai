CHANGELOG
=========

0.15
----

 * Add reranking support via the `/v1/rerank` endpoint, with the `rerank-3`, `rerank-3-lite`, `rerank-2.5`, `rerank-2.5-lite`, `rerank-2` and `rerank-2-lite` models and token usage from the `usage.total_tokens` field

0.11
----

 * Add a `baseUrl` argument to the model clients and the factory to target Voyage-compatible endpoints

0.8
---

 * [BC BREAK] `VoyageContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.1
---

 * Add the bridge
