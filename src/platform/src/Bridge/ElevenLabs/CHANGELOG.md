CHANGELOG
=========

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
