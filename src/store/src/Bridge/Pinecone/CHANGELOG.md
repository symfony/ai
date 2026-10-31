CHANGELOG
=========

1.0
---

 * Deprecate the `topK` option of `Store::query()`, use `limit` instead
 * [BC BREAK] Replace the `dimension`, `metric`, `cloud` and `region` options of `Store::setup()` by the `dimensions`, `metric`, `cloud` and `region` arguments of `Store`, the dimensions default to 1536

0.14
----

 * Add `count` method

0.7
---

 * Add support for `includeMetadata` and `includeValues` options

0.1
---

 * Add the bridge
