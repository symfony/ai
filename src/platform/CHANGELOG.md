CHANGELOG
=========

0.8
---

* Add support for multiple system messages in `MessageBag` by concatenating them with break lines

0.7
---

 * Add `TraceablePlatform` profiler decorator moved from AI Bundle
 * Add `asFile()` method to `BinaryResult` and `DeferredResult` for saving binary content to a file
 * Add typed streaming deltas (`TextDelta`, `ThinkingDelta`, `ThinkingStart`, `ThinkingSignature`, `ToolCallStart`, `ToolInputDelta`, `BinaryDelta`, `ChoiceDelta`, `ToolCallComplete`, `ThinkingComplete`) implementing `DeltaInterface`
 * [BC BREAK] Remove `Symfony\AI\Platform\Bridge\Ollama\OllamaMessageChunk`; Ollama streams now yield semantic deltas (`TextDelta`, `ThinkingDelta`, `ToolCallComplete`, `TokenUsage`) like the other bridges
 * Add generic `MetadataDelta` streaming support and use it for Perplexity citations/search results instead of provider-specific stream delta classes and listeners
 * Remove `DeltaInterface` from `BinaryResult`, `ChoiceResult`, and `ToolCallResult`
 * Remove `Usage` and `ThinkingContent` classes in favor of `TokenUsage` and `ThinkingComplete`
 * Add `DeltaEvent` replacing `ChunkEvent` in `ListenerInterface`
 * Add reranking support via `RerankingResult`, `RerankingEntry`, and `Capability::RERANKING`
 * Add `description` and `example` properties to `#[With]` attribute
 * Generate JSON schema from Symfony Validator constraints when available
 * Add `asTextStream()` method to `DeferredResult` to get a stream of `TextDelta` objects only
 * Add `reasoning_content` serialization in shared `AssistantMessageNormalizer` for OpenAI-compatible endpoints

0.6
---

 * [BC BREAK] Change `Symfony\AI\Platform\Contract\JsonSchema\Factory` constructor signature in order to make schema generation extensible

0.4
---

 * Add thinking support to `AssistantMessage`
 * Add support for object serialization in template variables via `template_vars` option
 * Add support for populating existing object instances in structured output via `response_format` option

0.3
---

 * Add `StreamListenerInterface` to hook into response streams
 * [BC BREAK] Change `TokenUsageAggregation::__construct()` from variadic to array
 * Add `TokenUsageAggregation::add()` method to add more token usages
 * [BC BREAK] `CachedPlatform` has been renamed `CachePlatform` and moved as a bridge, please require `symfony/ai-cache-platform` and use `Symfony\AI\Platform\Bridge\Cache\CachePlatform`
 * [BC BREAK] `Metadata::merge()` method signature has changed to accept `Metadata` instead of array
 * [BC BREAK] Behavior of `Metadata::add()` has changed to merge existing keys instead of overwriting them
 * [BC BREAK] Move `Symfony\AI\Platform\Serializer\StructuredOutputSerializer` to `Symfony\AI\Platform\StructuredOutput\Serializer`

0.2
---

 * [BC BREAK] Change `ChoiceResult::__construct()` from variadic to accept array of `ResultInterface`

0.1
---

 * Add the component
