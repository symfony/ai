CHANGELOG
=========

0.1
---

 * Add support for Albert API for French/EU data sovereignty
 * Add unified abstraction layer for interacting with various AI models and providers
 * Add support for 13+ AI providers:
   - OpenAI (GPT-4, GPT-3.5, DALL·E, Whisper)
   - Anthropic (Claude models via native API and AWS Bedrock)
   - Google (Gemini models with server-side tools support)
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


