CHANGELOG
=========

1.0
---

 * [BC BREAK] Rename the `embeddingsDimension` and `strategy` arguments of `Store` and `StoreFactory` to `dimensions` and `metric`

0.15
----

 * Add support for SurrealDB 3
 * [BC BREAK] `Store::setup()` creates an `HNSW` index instead of an `MTREE` one
 * Add `forceDatabaseCreation` option to `Store::setup()` to create the namespace and database
 * Add `ef` option to `Store::query()`

0.14
----

 * Add `count` method

0.11
----

 * Normalize the `endpoint` URL and tolerate a trailing slash
 * [BC BREAK] Rename the `$endpointUrl` constructor/factory argument to `$endpoint`
 * Introduce a `StoreFactory`
 * [BC BREAK] Add support for `ScopingHttpClient` in `Store`
 * [BC BREAK] The `endpointUrl` parameter for `Store` has been removed

0.1
---

 * Add the bridge
