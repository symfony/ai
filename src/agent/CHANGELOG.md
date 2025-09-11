CHANGELOG
=========

0.1
---

 * Add Agent class as central orchestrator for AI interactions through the Platform component
 * Add input/output processing pipeline:
   - `InputProcessorInterface` for pre-processing messages and options
   - `OutputProcessorInterface` for post-processing LLM responses
   - `AgentAwareInterface` for processors requiring agent access
   - `SystemPromptInputProcessor` for system prompt injection
   - `ModelOverrideInputProcessor` for dynamic model switching
 * Add comprehensive tool system:
   - `#[AsTool]` attribute for simple tool registration
   - `ReflectionToolFactory` for auto-discovering tools with attributes
   - `MemoryToolFactory` for manual tool registration
   - `ChainFactory` for combining multiple factories
   - Automatic JSON Schema generation for parameter validation
   - Tool call execution with argument resolution
   - `ToolCallsExecuted` and `ToolCallArgumentsResolved` events
   - `FaultTolerantToolbox` for graceful error handling
 * Add built-in tools:
   - `SimilaritySearch` for RAG/vector store searches
   - `Agent` allowing agents to use other agents as tools
   - `Clock` for current date/time
   - `Brave` for web search integration
   - `Crawler` for web page crawling
   - `Mapbox` for geocoding addresses to coordinates and reverse geocoding
   - `OpenMeteo` for weather information
   - `SerpApi` for search engine results
   - `Tavily` for AI-powered search
   - `Wikipedia` for Wikipedia content retrieval
   - `YouTubeTranscriber` for YouTube video transcription
 * Add structured output support:
   - PHP class output with automatic conversion from LLM responses
   - Array structure output with JSON schema validation
   - `ResponseFormatFactory` for schema generation
   - Symfony Serializer integration
 * Add memory management system:
   - `MemoryInputProcessor` for injecting contextual memory
   - `StaticMemoryProvider` for fixed contextual information
   - `EmbeddingProvider` for vector-based memory retrieval
   - Dynamic memory control with `use_memory` option
   - Extensible `MemoryInterface` for custom providers
 * Add advanced features:
   - Tool filtering to limit available tools per agent call
   - Tool message retention option for context preservation
   - Multi-method tools support in single classes
   - Tool parameter validation with `#[With]` attribute
   - Stream response support for real-time output
   - PSR-3 logger integration throughout
   - Symfony EventDispatcher integration
 * Add model capability detection before processing
 * Add comprehensive type safety with full PHP type hints
 * Add clear exception hierarchy for different error scenarios
 * Add translation support for system prompts
