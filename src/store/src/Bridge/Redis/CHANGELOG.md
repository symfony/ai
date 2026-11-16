CHANGELOG
=========

1.0
---

 * [BC BREAK] Replace the `vector_size`, `index_method` and `extra_schema` options of `Store::setup()` by the `dimensions`, `indexMethod` and `extraSchema` arguments of `Store`
 * [BC BREAK] `Store::setup()` creates an index with 1536 dimensions by default instead of 3072
 * [BC BREAK] Rename the `Distance` enum to `Metric`, and the `distance` argument of `Store` to `metric`

0.14
----

 * Add `count` method

0.1
---

 * Add the bridge
