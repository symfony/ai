CHANGELOG
=========

0.4
---

 * Fix cache key computation for `MessageBag` and `MessageInterface` inputs: the key is now derived from the message content instead of the random identifier of the latest user message, so identical inputs built from separate instances now hit the cache (issue #2192)
 * The cache key now reflects the whole message bag (system prompt and prior turns included) instead of only the latest user message
 * `MessageBag` inputs without a user message and bare `MessageInterface` inputs are now cacheable
 * Bypass the cache instead of throwing when the input cannot be normalized into a deterministic key
 * Derive the cache key through the platform `Contract` serializer (without a model bound to the context) instead of a dedicated manual walker, so the normalized representation stays in sync with the request payload
 * Fix caching of `ToolCallResult`: tool calls are now normalized inline by `ResultNormalizer` instead of being delegated to the serializer, so a tool-call result is cacheable with the default serializer (which no longer requires a dedicated tool-call normalizer to be registered)
 * Preserve the `signature` of `TextResult` and `ToolCall` across the cache round-trip, matching the existing `ThinkingResult` behavior, so provider-scoped signatures (e.g. Gemini/Vertex AI `thoughtSignature`) survive a cache hit
 * Bypass the cache for streaming responses (`stream` option) instead of throwing when the `StreamResult` cannot be normalized into a cacheable representation
 * Fail open on the store and restore paths: an uncacheable result is returned live instead of breaking the request, and a stale or corrupted cache entry (including a payload shape from a previous version) is dropped and re-fetched instead of throwing during denormalization
 * The constructor-level `cacheKey` now acts as the default cache namespace: caching engages even without a per-call `prompt_cache_key` when `cacheKey` is set (the parameter was previously unused); an empty per-call key still opts out of caching for that call
 * Build the cache key with a `.` delimiter between the namespace, the camelized model name and the content hash instead of a plain concatenation, to avoid collisions at the namespace/model boundary; existing entries miss and are re-populated
 * Add `CachePlatform::invalidateTags()` to drop cached entries by tag; each entry is tagged with the camelized model name and `namespace.<cache key>`, so a model or a whole namespace can be invalidated

0.3
---

 * Add the bridge
