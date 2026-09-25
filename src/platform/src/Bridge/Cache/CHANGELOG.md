CHANGELOG
=========

0.13
----

 * Fix the cache key computation for `MessageBag` and `MessageInterface` inputs: the key is now derived from the message content instead of the random identifier carried by the bag, so identical inputs built from separate instances hit the cache
 * Fix the cache key computation for `array` inputs: the array is normalized through the platform contract and hashed instead of being used verbatim, so it no longer makes the cache adapter throw on a PSR-6 reserved character and an object nested in the array contributes its content to the key instead of being flattened into an empty JSON object
 * Fix the caching of a `ToolCallResult`: tool calls are normalized inline by `ResultNormalizer` instead of being delegated to the serializer, so they are cacheable with the default serializer, which no longer requires a dedicated tool call normalizer to be registered
 * Preserve the signature of `TextResult` and `ToolCall` across the cache round-trip, as already done for `ThinkingResult`, so a provider scoped signature (e.g. the Gemini and Vertex AI `thoughtSignature`) survives a cache hit
 * [BC BREAK] Read the constructor level `cacheKey`, which was previously ignored: it now acts as the default cache namespace, so caching engages without a per-call `prompt_cache_key`; an empty per-call key still opts the call out of caching
 * Include the invocation options in the cache key, so the same input answered with a different temperature, tool set or response format no longer shares a cached entry
 * Strip `CachePlatform::OPTION_CACHE_KEY` and `CachePlatform::OPTION_CACHE_TTL` from the options handed to the decorated platform on every path, not only when the call is cached
 * Throw an `InvalidArgumentException` when the cache namespace holds a PSR-6 reserved character, instead of letting the cache adapter fail with an opaque error
 * Bypass the cache instead of breaking the request for a streamed invocation, an input no deterministic key can be derived from, a result the serializer cannot handle, and a stale or corrupted cache entry
 * Add `MessageCacheKeyGenerator` to key a bare `MessageInterface` input, and `CachePlatform::OPTION_CACHE_KEY`/`CachePlatform::OPTION_CACHE_TTL` to reference the invocation options without repeating their name
 * Add `CachePlatform::invalidateTags()` to drop cached entries by tag; each entry is tagged with the camelized model name and `namespace.<cache key>`, so a model or a whole namespace can be invalidated
 * Build the cache key by joining its segments (the namespace, the camelized model name, the input hash and the options hash) with a `.` delimiter instead of concatenating them, to avoid collisions at the namespace/model boundary; entries written by a previous version are no longer read and are re-populated

0.11
----

 * Add `CacheKeyGenerator` strategy and a default set (`MessageBag`, `DocumentUrl`, `ImageUrl`, `File`/`Audio`), so `CachePlatform` can cache document, OCR and audio-transcription tasks, not just `string`/`array`/`MessageBag` inputs; custom input types can be supported by registering an additional generator

0.3
---

 * Add the bridge
