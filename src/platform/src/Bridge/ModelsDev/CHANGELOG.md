CHANGELOG
=========

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.6
---

 * Add well-known base URLs for providers with dedicated npm packages
 * Skip specialized bridge check in `ModelsDevPlatformFactory` when a custom `$baseUrl` is provided

0.4
---

 * Add models.dev bridge with auto-discovered model catalogs
