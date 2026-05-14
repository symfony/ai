CHANGELOG
=========

0.9
---

 * Add `HybridQuery` support to `Store`
 * Introduce a `StoreFactory`
 * [BC BREAK] Add support for `ScopingHttpClient` in `Store`
 * [BC BREAK] The `endpointUrl` parameter for `Store` has been removed
 * [BC BREAK] The `apiKey` parameter for `Store` has been removed
 * [BC BREAK] `Store::query()` no longer reads `$options['q']`; use `HybridQuery` to combine vector and full-text search

0.1
---

 * Add the bridge
