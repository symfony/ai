CHANGELOG
=========

0.8
---

 * [BC BREAK] `AnthropicContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * [BC BREAK] `ResultConverter` now returns `MultiPartResult` for responses with multiple results
 * Add support for code execution results

0.7
---

 * Extend prompt caching support for tool definitions
 * [BC BREAK] Stream responses now yield `TextDelta`, `ThinkingDelta`, `ThinkingSignature`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, and streamed `TokenUsage` deltas

0.6
---

 * Add Anthropic prompt caching support via `cacheRetention` parameter on `ModelClient` and `PlatformFactory`
 * Add structured output support

0.4
---

 * Add thinking support to Anthropic normalizer
 * Parse thinking events in ResultConverter
 * Add Capability::THINKING to thinking-capable models and wire up thinking options in ModelClient

0.1
---

 * Add the bridge
