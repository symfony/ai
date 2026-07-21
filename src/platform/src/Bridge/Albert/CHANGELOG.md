CHANGELOG
=========

0.12
----

 * [BC BREAK] The `baseUrl` must no longer include the API version (e.g. use `https://albert.api.etalab.gouv.fr`
   instead of `https://albert.api.etalab.gouv.fr/v1`), aligning with the Generic bridge convention

0.11
----

 * Tolerate a trailing slash on the base URL instead of rejecting it

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.1
---

 * Add the bridge
