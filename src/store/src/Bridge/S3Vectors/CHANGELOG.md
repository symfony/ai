CHANGELOG
=========

1.0
---

 * Deprecate the `topK` option of `Store::query()`, use `limit` instead
 * [BC BREAK] Replace the `dimension`, `distanceMetric`, `dataType`, `metadata`, `encryption` and `tags` options of `Store::setup()` by the `dimensions`, `metric`, `dataType`, `metadataConfiguration`, `kmsKeyId` and `tags` arguments of `Store`, the dimensions default to 1536

0.14
----

 * Add `count` method

0.6
---

 * Add the bridge
