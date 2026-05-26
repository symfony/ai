CHANGELOG
=========

0.14
----

 * Add `count` method

0.13
----

 * Add an optional `EmbeddingFunction` argument to `Store` and `StoreFactory::create()` so a `TextQuery` can be embedded client-side; `supports(TextQuery::class)` now only returns `true` when one is configured

0.8
---

 * Introduce a `StoreFactory`

0.4
---

 * Add `remove` method
 * Add support for store management methods `setup` and `drop`

0.1
---

 * Add the bridge
