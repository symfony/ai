CHANGELOG
=========

0.16
----

 * [BC BREAK] Replace the `dimensions` option of `Store::setup()` by the `dimensions` argument of `Store`, `Store::fromPdo()` and `Store::fromDbal()`
 * [BC BREAK] Rename the `Distance` enum to `Metric`, and the `distance` argument of `Store`, `Store::fromPdo()` and `Store::fromDbal()` to `metric`

0.14
----

 * Add `count` method

0.7
---

 * Add support for cosine distance in MariaDB store

0.1
---

 * Add the bridge
