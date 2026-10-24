CHANGELOG
=========

0.16
----

 * [BC BREAK] Replace the `fields` option of `Store::setup()` by the `indexFields` argument of `Store`
 * [BC BREAK] Rename the `embeddingsDimension` and `similarity` arguments of `Store` to `dimensions` and `metric`
 * [BC BREAK] Rename the `Similarity` enum to `Metric`

0.15
----

 * Add `similarity` argument to `Store` to choose the similarity function of the vector index created by `setup()`
 * `Store::setup()` rejects a `fields` option entry for the vector field

0.14
----

 * Add `count` method

0.7
---

 * Add support for `Store::remove()` method

0.1
---

 * Add the bridge
