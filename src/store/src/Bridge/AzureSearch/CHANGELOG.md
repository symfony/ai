CHANGELOG
=========

0.15
----

 * Read the `limit` option in `SearchStore::query()` and run an approximate vector query by default; pass the `exhaustive` option to run an exhaustive KNN

0.14
----

 * Add `count` method

0.11
----

 * Normalize the `endpoint` URL and tolerate a missing trailing slash on path-prefixed endpoints

0.7
---

 * [BC BREAK] Add support for `ScopingHttpClient` in `SearchStore`
 * [BC BREAK] The `endpointUrl` parameter for `SearchStore` has been removed
 * [BC BREAK] The `apiKey` parameter for `SearchStore` has been removed
 * [BC BREAK] The `apiVersion` parameter for `SearchStore` has been removed

0.4
---

 * Add `remove` method

0.1
---

 * Add the bridge
