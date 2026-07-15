CHANGELOG
=========

0.11
----

 * Normalize the `endpoint` URL and tolerate a trailing slash
 * [BC BREAK] Rename the `$host` constructor argument to `$endpoint`
 * [BC BREAK] Create the `uuid` column as a `STRING` attribute instead of a `TEXT` field so documents can be removed by id; existing tables must be recreated (`drop()` + `setup()`)

0.1
---

 * Add the bridge
