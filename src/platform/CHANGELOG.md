CHANGELOG
=========

0.11
----

 * [BC BREAK] Remove `Capability::INPUT_MULTIPLE` from all embedding models since every embedding bridge already accepts multiple inputs in a single API call
 * Add `ResultConvertedEvent` and `ResultErrorEvent`, dispatched when the deferred result is actually converted (or conversion fails) instead of when the not-yet-resolved `ResultEvent` fires, so listeners can act on the resolved result
 * Add `provider` and `context` arguments to `#[Schema]` plus `SchemaProviderInterface` to contribute JSON Schema fragments computed at runtime (env, database, services) for tool parameters and structured output properties; `provider` accepts any container service ID and `context` is passed to `getSchemaFragment()`
 * Add `PartialJsonParser` for recovering partial JSON from streaming output
 * Add `DeferredResult::asPartialJsonStream()` yielding the largest recoverable JSON value from each streamed delta
 * Add streaming support for structured output via `DeferredResult::asStreamedObject()`; combining `stream: true` with `response_format: SomeClass::class` no longer throws and yields progressively populated typed objects through the new `PartialObjectStreamListener`
 * Add routing-aware mock test provider (`Test\MockPlatformFactory`, `Test\MockModelClient`, `Test\MockResultConverter`, `Test\MockModelCatalog`) for any-result-type mocks in tests, complementing `Test\InMemoryPlatform`
 * [BC BREAK] Rework `ToolCallMessage` to hold `Content\ContentInterface` parts (variadic constructor) instead of a single string content, mirroring `UserMessage`: `getContent()` now returns `Content\ContentInterface[]` and `asText()` returns the flattened text. `Message::ofToolCall()` accepts strings, `\Stringable`, and `Content\ContentInterface` values, upcasting strings to `Content\Text`. This enables multimodal tool results (e.g. text and images): the Anthropic and Amazon Bedrock (Nova/Converse) bridges render them as `tool_result` content blocks, the Gemini and Vertex AI bridges attach them as `inline_data` parts next to the `functionResponse`, and bridges without multimodal tool-result support degrade to text.
 * Add `ServerException`, thrown on transient server errors (HTTP 5xx) by `HttpStatusErrorHandlingTrait` and the bridge converters so consumers can retry without parsing error messages
 * Add `Capability::TEXT_TO_SPEECH_ASYNC`, `Capability::VIDEO_FRAME_TO_FRAME`, `Capability::VIDEO_WITH_SUBJECT`, and `Capability::MUSIC` cases
 * Add OpenAI image generation for the `gpt-image-*` models (`gpt-image-1`, `gpt-image-1-mini`, `gpt-image-1.5`, `gpt-image-2`), routed to the images endpoint by the model catalog
 * Add OpenAI image editing via the `images/edits` endpoint: pass the source image as a `Content\Image` through the `image` option of `Platform::invoke()`
 * [BC BREAK] Rename the OpenAI `DallE` bridge to `Image` (`Bridge\OpenAi\DallE` → `Bridge\OpenAi\Image`, and the `Bridge\OpenAi\DallE\*` namespace → `Bridge\OpenAi\Image\*`) and remove the `dall-e-2`/`dall-e-3` catalog entries retired by OpenAI
 * [BC BREAK] Replace the bridge-specific `ImageResult`, `Base64Image`, and `UrlImage` classes of the OpenAI image bridge with the generic `Result\BinaryResult` (single image) and `Result\MultiPartResult` (multiple images), matching the other multi-modal bridges

0.10
----

 * Add `RawSseStream` to parse Server-Sent Events from backends that omit the `text/event-stream` content type (used by the OpenResponses bridge for the ChatGPT Codex backend); `SseStream` now decodes only content-type-advertised SSE
 * Add `IncompleteStreamException`, thrown by bridge converters when a stream ends before its terminal event
 * Throw `ModelNotFoundException` on HTTP 404 responses in the shared `HttpStatusErrorHandlingTrait`
 * Add `JsonBodyEncodingTrait` so model clients can encode JSON request bodies without aborting on malformed UTF-8
 * Add support for passing a fully defined `Model` instance to `Platform::invoke()` (and `Provider::invoke()`) instead of a model name string, bypassing the model catalog; widen `ProviderInterface::supports()` to `string|Model` to route a model instance to the first provider whose model clients accept it
 * Add in-place `MessageBag::prepend()` and `MessageBag::removeSystemMessage()`
 * Add `MessageBag::withoutToolMessages()` to strip tool-call messages and tool-call-only assistant messages

0.9
---

 * Add `ValidatorSubscriber` to validate structured output using Symfony Validator
 * Add support for multiple system messages in `MessageBag`
 * Add `Message\Content\Json` for keeping structured-output payloads typed in the message bag. `Message::ofAssistant()` now also accepts arbitrary objects (wrapped as `Json`); bridges (Anthropic, Bedrock Nova, Gemini, VertexAI, OpenResponses, AiMlApi, base normalizer) serialize the wrapped value to a JSON string when sending the message back to the provider.
 * [BC BREAK] Rework `AssistantMessage` to hold `ContentInterface` parts (variadic constructor) instead of a single string content plus separate tool-call/thinking fields. Adds `Message\Content\Thinking`, `Message\Content\ExecutableCode`, and `Message\Content\CodeExecution` content classes, and makes `Result\ToolCall` implement `ContentInterface`. `Message::ofAssistant()` accepts strings, `ContentInterface`, and `ResultInterface` values, mapping `TextResult`/`ThinkingResult`/`ToolCallResult`/`ExecutableCodeResult`/`CodeExecutionResult`/`MultiPartResult` to their content equivalents; result types without a known mapping throw `InvalidArgumentException` so unhandled cases surface instead of being silently dropped.
 * Add optional `signature` field to `Message\Content\Text`, `Result\ToolCall`, and `Result\TextResult` for provider-scoped signatures (currently used by Gemini/Vertex AI for `thoughtSignature` round-trip).
 * Memoize conversion failures in `DeferredResult::getResult()` so subsequent calls re-throw the cached exception instead of re-running the converter
 * Surface tool calls executed by the `ClaudeCode` and `Codex` CLI bridges as `ToolCallResult` parts of a `MultiPartResult`, mirroring the inference-API behavior of the Anthropic and Gemini bridges

0.8
---

 * [BC BREAK] Reduce visibility of `ImageResult::$revisedPrompt` to `private readonly`; use `getRevisedPrompt()` instead
 * Add `MultiPartResult` for exposing the parts inside a message
 * Add `ExecutableCodeResult`, `CodeExecutionResult` for exposing the executed code blocks and results
 * [BC BREAK] Replace variadic constructor parameters with array parameters in `VectorResult`, `ToolCallResult`, `RerankingResult`, `ToolCallComplete`, and `ImageResult` (OpenAI DallE bridge)
 * [BC BREAK] Rename `#[With]` attribute to `#[Schema]` and `WithAttributeDescriber` to `SchemaAttributeDescriber`
 * Add `ref` property to `#[Schema]` attribute to allow providing schema as file
 * Add support for `DiscriminatorMap` and `DateTimeImmutable` for structured output and tool calls
 * [BC BREAK] `Contract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Change `public array $calls` and `public \WeakMap $resultCache` to private in `TraceablePlatform` - use `getCalls()` and `getResultCache()` instead
 * Add `ProviderInterface` and `Provider` abstraction for inference backends, with `Platform` becoming a router over one or more providers
 * Add `ModelRouterInterface` with `CatalogBasedModelRouter` as default strategy, and `CompositeModelCatalog` merging provider catalogs
 * Add `ModelRoutingEvent` dispatched by `Platform` before resolution, allowing listeners to observe/modify routing or short-circuit it by setting a provider
 * [BC BREAK] `Platform` constructor signature changed from `(modelClients, resultConverters, modelCatalog, contract, eventDispatcher)` to `(providers, modelRouter, eventDispatcher)`
 * Add `HttpStatusErrorHandlingTrait` and expose API errors (`AuthenticationException`, `BadRequestException`, `RateLimitExceededException`) across the Mistral, DeepSeek, Cerebras, Perplexity, Cohere, Gemini and OpenAI (Embeddings, DallE, TextToSpeech) bridges

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
