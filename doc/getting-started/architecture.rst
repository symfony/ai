Architecture Overview
=====================

Symfony AI follows a layered, modular architecture that promotes flexibility, testability, and clean separation 
of concerns. This document explains the design principles and how components work together.

Design Principles
-----------------

**Modularity**
    Each component is independent and can be used standalone or combined with others. This allows you to use 
    only what you need without unnecessary dependencies.

**Abstraction Over Implementation**
    Core interfaces define contracts that multiple implementations can fulfill. This enables switching between 
    AI providers without changing application code.

**Dependency Injection**
    Components are designed for dependency injection, making them testable and allowing easy customization 
    through service configuration.

**Type Safety**
    Extensive use of PHP's type system ensures compile-time safety and excellent IDE support for 
    autocompletion and refactoring.

**Immutability**
    Value objects like Messages and Results are immutable, preventing unexpected state changes and making 
    code more predictable.

Component Layers
----------------

.. code-block:: text

    ┌─────────────────────────────────────────────────┐
    │            Application Layer                     │
    │         (Your Symfony Application)               │
    └─────────────────────────────────────────────────┘
                          │
    ┌─────────────────────────────────────────────────┐
    │              Bundle Layer                        │
    │     (AI Bundle, MCP Bundle)                      │
    │   • Service Configuration                        │
    │   • Dependency Injection                         │
    │   • Developer Tools                              │
    └─────────────────────────────────────────────────┘
                          │
    ┌─────────────────────────────────────────────────┐
    │            High-Level Components                 │
    │         (Agent, Chat, Toolbox)                   │
    │   • Business Logic                               │
    │   • Workflow Management                          │
    │   • Tool Orchestration                           │
    └─────────────────────────────────────────────────┘
                          │
    ┌─────────────────────────────────────────────────┐
    │             Core Components                      │
    │       (Platform, Store, MCP SDK)                 │
    │   • Provider Abstraction                         │
    │   • Data Models                                  │
    │   • Core Interfaces                              │
    └─────────────────────────────────────────────────┘
                          │
    ┌─────────────────────────────────────────────────┐
    │              Bridge Layer                        │
    │    (Provider-Specific Implementations)           │
    │   • OpenAI, Anthropic, Gemini, etc.              │
    │   • MariaDB, MongoDB, Pinecone, etc.             │
    └─────────────────────────────────────────────────┘

Core Components
---------------

Platform Component
~~~~~~~~~~~~~~~~~~

The Platform component is the foundation that abstracts different AI providers::

    <?php

    interface PlatformInterface
    {
        public function invoke(
            Model $model,
            mixed $input,
            array $options = []
        ): ResultInterface;
    }

Key concepts:

* **Platform**: Handles communication with AI provider APIs
* **Model**: Represents a specific AI model with its capabilities
* **Result**: Encapsulates the response from the AI model
* **Message**: Structured representation of conversation elements

Agent Component
~~~~~~~~~~~~~~~

The Agent component provides high-level abstractions for AI interactions::

    <?php

    interface AgentInterface
    {
        public function call(
            MessageBagInterface $messages,
            array $options = []
        ): ResultInterface;
    }

Features:

* **Agent**: Orchestrates AI interactions with tools and processors
* **Toolbox**: Manages callable tools for the AI
* **Processors**: Transform input/output for specific behaviors
* **Memory**: Adds contextual awareness to conversations

Store Component
~~~~~~~~~~~~~~~

The Store component handles vector storage for RAG and semantic search::

    <?php

    interface StoreInterface
    {
        public function add(VectorDocument ...$documents): void;
    }

    interface VectorStoreInterface
    {
        public function query(Vector $vector, array $options = []): array;
    }

Components:

* **Store**: Persists vector embeddings
* **Indexer**: Converts documents to vectors and stores them
* **Document**: Represents textual content with metadata
* **Vector**: Mathematical representation for similarity search

Data Flow
---------

Request Flow
~~~~~~~~~~~~

.. code-block:: text

    User Input
        ↓
    MessageBag Creation
        ↓
    Input Processors
        ↓
    Platform Invocation
        ↓
    Provider API Call
        ↓
    Result Creation
        ↓
    Output Processors
        ↓
    Final Response

Tool Calling Flow
~~~~~~~~~~~~~~~~~

.. code-block:: text

    User Message
        ↓
    Agent Analysis
        ↓
    Tool Selection
        ↓
    Tool Execution
        ↓
    Result Integration
        ↓
    Response Generation

RAG Flow
~~~~~~~~

.. code-block:: text

    User Query
        ↓
    Query Embedding
        ↓
    Vector Search
        ↓
    Context Retrieval
        ↓
    Augmented Prompt
        ↓
    AI Generation
        ↓
    Contextual Response

Message Architecture
--------------------

Messages are the core data structure for AI interactions::

    <?php

    // Message hierarchy
    MessageInterface
    ├── UserMessage
    ├── AssistantMessage
    ├── SystemMessage
    └── ToolCallMessage

    // Content types
    ContentInterface
    ├── Text
    ├── Image
    ├── Audio
    ├── Document
    └── DocumentUrl

Each message:
* Has a unique UUID v7 identifier
* Contains one or more content items
* Is immutable once created
* Can be serialized/deserialized

Provider Bridges
----------------

Provider bridges implement platform-specific logic::

    <?php

    namespace Symfony\AI\Platform\Bridge\OpenAi;

    class PlatformFactory
    {
        public static function create(string $apiKey): Platform
        {
            // Creates configured OpenAI platform
        }
    }

    class Gpt extends Model
    {
        public const GPT_4O = 'gpt-4o';
        public const GPT_4O_MINI = 'gpt-4o-mini';
        // Model-specific configuration
    }

Each bridge provides:
* Platform factory for easy initialization
* Model classes with predefined configurations
* Result converters for provider-specific responses
* Contract normalizers for API compatibility

Extension Points
----------------

Symfony AI is designed for extensibility:

Custom Tools
~~~~~~~~~~~~

.. code-block:: php

    #[AsTool('my_tool', 'Tool description')]
    class MyTool
    {
        public function __invoke(string $param): string
        {
            // Tool implementation
        }
    }

Custom Processors
~~~~~~~~~~~~~~~~~

.. code-block:: php

    class MyProcessor implements InputProcessorInterface
    {
        public function processInput(Input $input): void
        {
            // Modify messages or options
        }
    }

Custom Stores
~~~~~~~~~~~~~

.. code-block:: php

    class MyStore implements StoreInterface, VectorStoreInterface
    {
        public function add(VectorDocument ...$documents): void
        {
            // Store implementation
        }

        public function query(Vector $vector, array $options = []): array
        {
            // Query implementation
        }
    }

Service Container Integration
-----------------------------

In Symfony applications, components are wired through dependency injection:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        
        agent:
            default:
                platform: 'ai.platform.openai'
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: 'gpt-4o-mini'
                tools: 
                    - '@App\Tool\MyCustomTool'

Services are automatically tagged and configured:

* Tools with ``#[AsTool]`` are auto-registered
* Platforms are available as ``ai.platform.{name}``
* Agents are available as ``ai.agent.{name}``
* Stores are available as ``ai.store.{type}.{name}``

Event System
------------

Symfony AI integrates with Symfony's event dispatcher::

    <?php

    // Tool execution events
    class ToolCallsExecuted extends Event
    {
        public array $toolCallResults;
        public ?ResultInterface $result = null;
    }

    // Listen to tool executions
    $dispatcher->addListener(
        ToolCallsExecuted::class,
        function (ToolCallsExecuted $event) {
            // Process tool results
        }
    );

Testing Architecture
--------------------

Components include testing utilities:

* **InMemoryPlatform**: Mock platform for unit tests
* **InMemoryStore**: Vector store for testing
* **Fixture classes**: Pre-configured test data
* **Assertions**: Custom assertions for AI-specific testing

Performance Considerations
--------------------------

* **Parallel Processing**: Platform supports concurrent API calls
* **Streaming**: Reduces latency for long responses
* **Caching**: Stores support caching for repeated queries
* **Lazy Loading**: Services are instantiated on-demand
* **Connection Pooling**: HTTP clients reuse connections

Security Architecture
---------------------

* **API Key Management**: Environment variables and Symfony secrets
* **Input Validation**: Automatic parameter validation
* **Content Filtering**: Provider-level content moderation
* **Access Control**: Integration with Symfony Security
* **Tool Authorization**: ``#[IsGrantedTool]`` attribute

Next Steps
----------

* Explore individual components in detail:
  * :doc:`../components/platform`
  * :doc:`../components/agent`
  * :doc:`../components/store`
* Learn about specific features:
  * :doc:`../features/tool-calling`
  * :doc:`../features/rag`
* See implementation examples:
  * :doc:`../guides/building-chatbot`