CHANGELOG
=========

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
