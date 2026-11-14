CHANGELOG
=========

1.0
---

 * Allow passing a DSN instead of a `\PDO` instance to `Store` and `VecStore`, with the `connectionOptions` argument for the PDO options; the connection is opened on the first query
 * Make `StoreFactory::create()` and `StoreFactory::createVecStore()` connect on the first query instead of when called
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
