CHANGELOG
=========

0.7
---

 * Add `asFile()` method to `BinaryResult` and `DeferredResult` for saving binary content to a file
 * Add reranking support via `RerankingResult`, `RerankingEntry`, and `Capability::RERANKING`
 * Add `description` and `example` properties to `#[With]` attribute
 * Generate JSON schema from Symfony Validator constraints when available

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

 * Add nullables as required in structured outputs
 * Add support for Albert API for French/EU data sovereignty
 * Add unified abstraction layer for interacting with various AI models and providers
 * Add support for 16+ AI providers:
   - OpenAI (GPT-4, GPT-3.5, DALL·E, Whisper)
   - Anthropic (Claude models via native API and AWS Bedrock)
   - Google (VertexAi and Gemini models with server-side tools support)
   - Azure (OpenAI and Meta Llama models)
   - AWS Bedrock (Anthropic Claude, Meta Llama, Amazon Nova)
   - Mistral AI (language models and embeddings)
   - Meta Llama (via Azure, Ollama, Replicate, AWS Bedrock)
   - Ollama (local model hosting)
   - Replicate (cloud-based model hosting)
   - OpenRouter (Google Gemini, DeepSeek R1)
   - Voyage AI (specialized embeddings)
   - HuggingFace (extensive model support with multiple tasks)
   - TransformersPHP (local PHP-based transformer models)
   - LM Studio (local model hosting)
   - Cerebras (language models like Llama 4, Qwen 3, and more)
   - Perplexity (Sonar models, supporting search results)
   - AI/ML API (language models and embeddings)
   - Docker Model Runner (local model hosting)
   - Scaleway (language models like OpenAI OSS, Llama 4, Qwen 3, and more)
   - Cartesia (voice model that supports both text-to-speech and speech-to-text)
 * Add comprehensive message system with role-based messaging:
   - `UserMessage` for user inputs with multi-modal content
   - `SystemMessage` for system instructions
   - `AssistantMessage` for AI responses
   - `ToolCallMessage` for tool execution results
 * Add support for multiple content types:
   - Text, Image, ImageUrl, Audio, Document, DocumentUrl, File
 * Add capability system for runtime model feature detection:
   - Input capabilities: TEXT, MESSAGES, IMAGE, AUDIO, PDF, MULTIPLE
   - Output capabilities: TEXT, IMAGE, AUDIO, STREAMING, STRUCTURED
   - Advanced capabilities: TOOL_CALLING
 * Add multiple response types:
   - `TextResponse` for standard text responses
   - `VectorResponse` for embedding vectors
   - `BinaryResponse` for binary data (images, audio)
   - `StreamResponse` for Server-Sent Events streaming
   - `ChoiceResponse` for multiple choice responses
   - `ToolCallResponse` for tool execution requests
   - `ObjectResponse` for structured object responses
   - `RawHttpResponse` for raw HTTP response access
 * Add real-time response streaming via Server-Sent Events
 * Add parallel processing support for concurrent model requests
 * Add tool calling support with JSON Schema parameter validation
 * Add contract system with normalizers for cross-platform compatibility
 * Add HuggingFace task support:
   - Text Classification, Token Classification, Fill Mask
   - Question Answering, Table Question Answering
   - Sentence Similarity, Zero-Shot Classification
   - Object Detection, Image Segmentation
 * Add metadata support for responses
 * Add token usage tracking
 * Add temperature and parameter controls
 * Add exception handling with specific error types
 * Add support for embeddings generation across multiple providers
 * Add response promises for async operations
 * Add InMemoryPlatform and InMemoryRawResult for testing Platform without external Providers calls
 * Add tool calling support for Ollama platform
 * Allow beta feature flags to be passed into Anthropic model options
 * Add Ollama streaming output support
 * Add multimodal embedding support for Voyage AI
 * Use Responses API for Scaleway platform when using gpt-oss-120b
