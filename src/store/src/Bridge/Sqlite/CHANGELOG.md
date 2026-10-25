CHANGELOG
=========

0.16
----

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
