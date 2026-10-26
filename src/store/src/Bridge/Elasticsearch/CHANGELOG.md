CHANGELOG
=========

1.0
---

 * [BC BREAK] Remove the `dimensions` and `similarity` options of `Store::setup()`, use the arguments of `Store` instead
 * [BC BREAK] Rename the `similarity` argument of `Store` and `StoreFactory` to `metric`

0.14
----

 * Add `count` method
 * Introduce a `StoreFactory`
 * [BC BREAK] Add support for `ScopingHttpClient` in `Store`
 * [BC BREAK] The `endpoint` parameter for `Store` has been removed

0.11
----

 * Normalize the `endpoint` URL and tolerate a trailing slash

0.1
---

 * Add the bridge
