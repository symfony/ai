CHANGELOG
=========

0.8
---

 * [BC BREAK] `ElevenLabsContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * Replace `ModelCatalog` by `ElevenLabsApiCatalog`
 * Rename `ElevenLabsApiCatalog` to `ModelCatalog`
 * The `$apiCatalog` parameter from `PlatformFactory` has been removed

0.6
---

 * The `PlatformFactory` is now in charge of creating `ElevenLabsApiCatalog` if `apiCatalog` is provided as `true`

0.5
---

 * [BC BREAK] The `hostUrl` parameter for `ElevenLabsClient` has been removed
 * [BC BREAK] The `host` parameter for `ElevenLabsApiCatalog` has been removed
 * [BC BREAK] The `hostUrl` parameter for `PlatformFactory::create()` has been renamed to `endpoint`

0.3
---

 * Add support for using API options, e.g. voice_settings

0.1
---

 * Add the bridge
