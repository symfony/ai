CHANGELOG
=========

0.15
----

 * [BC BREAK] Move to the Perplexity Agent API (`POST /v1/agent`), replacing Sonar Chat Completions, and remove `Contract\FileUrlNormalizer` and `FinishReasonMapper`
 * Add the `fast`, `low`, `medium`, `high` and `xhigh` presets and the `perplexity/sonar` model to the model catalog
 * Add tool calling and streamed token usage
 * Deprecate the `sonar`, `sonar-pro`, `sonar-reasoning`, `sonar-reasoning-pro` and `sonar-deep-research` models, sent as their replacement preset

0.14
----

 * Add model information to token usage extraction

0.11
----

 * Throw `ServerException` on server errors (HTTP 5xx) instead of a generic `RuntimeException`
 * Add a `baseUrl` argument to the model client and the factory to target Perplexity-compatible endpoints
 * Raise a `RuntimeException` on unhandled HTTP error statuses before streaming, instead of returning an empty stream

0.10
----

 * Throw `ExceedContextSizeException` instead of `BadRequestException` when a 400 response reports a context overflow
 * Throw `ModelNotFoundException` when a 404 response reports a missing model

0.8
---

 * [BC BREAK] `PerplexityContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * HTTP 400/401/429 responses now throw dedicated exceptions (`BadRequestException`, `AuthenticationException`, `RateLimitExceededException`)

0.7
---

 * [BC BREAK] Streaming responses now yield `TextDelta`, `PerplexitySearchResults`, and `PerplexityCitations` deltas instead of raw strings and metadata arrays

0.1
---

 * Add the bridge
