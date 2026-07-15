CHANGELOG
=========

0.11
----

 * Tolerate an `endpoint` configured with or without a trailing slash
 * Forward speech-to-text options (`language_code`, `tag_audio_events`, `num_speakers`, `diarize`, `timestamps_granularity`, `additional_formats`, ...) to the ElevenLabs `/v1/speech-to-text` request body
 * Expose the additional transcript formats returned by ElevenLabs (e.g. SRT subtitles) through a typed `Transcript` object: when the `additional_formats` option is requested, the converter returns an `ObjectResult` wrapping a `Transcript` (readable via `ResultInterface::asObject()`), otherwise it returns a plain `TextResult` (readable via `asText()`); each format is a typed `AdditionalFormat` value object (with `requested_format`, `file_extension`, `content_type`, `is_base64_encoded` and `content`), accessible through `Transcript::getAdditionalFormats()`

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
