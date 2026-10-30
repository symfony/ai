CHANGELOG
=========

1.0
---

 * Deprecate the `maxItems` option of `Store::query()` and `VecStore::query()`, use `limit` instead
 * [BC BREAK] Rename the `vectorDimension` argument of `VecStore` and `StoreFactory::createVecStore()` to `dimensions`
 * [BC BREAK] Rename the `Distance` enum to `Metric`, and the `distance` argument of `VecStore` and `StoreFactory::createVecStore()` to `metric`

0.14
----

 * Add `count` method

0.8
---

 * Add `VecStore` for native vector search using the sqlite-vec extension

0.7
---

 * Add the bridge
