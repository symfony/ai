AI Bundle
=========

Symfony integration bundle for Symfony AI components.

Integrating:

* `Symfony AI Agent`_
* `Symfony AI Chat`_
* `Symfony AI Platform`_
* `Symfony AI Store`_

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-bundle

Configuration
-------------

Basic Example with OpenAI
~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        agent:
            default:
                model: 'gpt-4o-mini'

Advanced Example with Multiple Agents
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            anthropic:
                api_key: '%env(ANTHROPIC_API_KEY)%'
            azure:
                # multiple deployments possible
                gpt_deployment:
                    base_url: '%env(AZURE_OPENAI_BASEURL)%'
                    deployment: '%env(AZURE_OPENAI_GPT)%'
                    api_key: '%env(AZURE_OPENAI_KEY)%'
                    api_version: '%env(AZURE_GPT_VERSION)%'
            eleven_labs:
                host: '%env(ELEVEN_LABS_HOST)%'
                api_key: '%env(ELEVEN_LABS_API_KEY)%'
                output_path: '%env(ELEVEN_LABS_OUTPUT_PATH)%'
            gemini:
                api_key: '%env(GEMINI_API_KEY)%'
            perplexity:
                api_key: '%env(PERPLEXITY_API_KEY)%'
            vertexai:
                location: '%env(GOOGLE_CLOUD_LOCATION)%'
                project_id: '%env(GOOGLE_CLOUD_PROJECT)%'
            ollama:
                host_url: '%env(OLLAMA_HOST_URL)%'
        agent:
            rag:
                platform: 'ai.platform.azure.gpt_deployment'
                track_token_usage: true # Enable tracking of token usage for the agent, default is true
                model: 'gpt-4o-mini'
                memory: 'You have access to conversation history and user preferences' # Optional: static memory content
                prompt: # The system prompt configuration
                    text: 'You are a helpful assistant that can answer questions.' # The prompt text
                    include_tools: true # Include tool definitions at the end of the system prompt
                tools:
                    # Referencing a service with #[AsTool] attribute
                    - 'Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch'

                    # Referencing a service without #[AsTool] attribute
                    - service: 'App\Agent\Tool\CompanyName'
                      name: 'company_name'
                      description: 'Provides the name of your company'
                      method: 'foo' # Optional with default value '__invoke'

                    # Referencing a agent => agent in agent 🤯
                    - agent: 'research'
                      name: 'wikipedia_research'
                      description: 'Can research on Wikipedia'
            research:
                platform: 'ai.platform.anthropic'
                model: 'claude-3-7-sonnet'
                tools: # If undefined, all tools are injected into the agent, use "tools: false" to disable tools.
                    - 'Symfony\AI\Agent\Toolbox\Tool\Wikipedia'
                fault_tolerant_toolbox: false # Disables fault tolerant toolbox, default is true
            search_agent:
                platform: 'ai.platform.perplexity'
                model: 'sonar'
                tools: false
            audio:
                platform: 'ai.platform.eleven_labs'
                model: 'text-to-speech'
                tools: false
        store:
            chroma_db:
                # multiple collections possible per type
                default:
                    collection: 'my_collection'
            cache:
                research:
                    service: 'cache.app'
                    cache_key: 'research'
                    strategy: 'chebyshev'
            memory:
                ollama:
                    strategy: 'manhattan'
        vectorizer:
            # Reusable vectorizer configurations
            openai_embeddings:
                platform: 'ai.platform.openai'
                model:
                    name: 'text-embedding-3-small'
                    options:
                        dimensions: 512
            mistral_embeddings:
                platform: 'ai.platform.mistral'
                model: 'mistral-embed'
        indexer:
            default:
                loader: 'Symfony\AI\Store\Document\Loader\InMemoryLoader'
                vectorizer: 'ai.vectorizer.openai_embeddings'
                store: 'ai.store.chroma_db.default'

            research:
                loader: 'Symfony\AI\Store\Document\Loader\TextFileLoader'
                vectorizer: 'ai.vectorizer.mistral_embeddings'
                store: 'ai.store.memory.research'

Store Dependency Injection
--------------------------

When using multiple stores in your application, the AI Bundle provides flexible dependency injection through store aliases.
This allows you to inject specific stores into your services without conflicts, even when stores share the same name across different types.

For each configured store, the bundle automatically creates two types of aliases:

1. **Simple alias**: ``StoreInterface $storeName`` - Direct reference by store name
2. **Type-prefixed alias**: ``StoreInterface $typeStoreName`` - Reference with store type prefix in camelCase

.. code-block:: yaml

    ai:
        store:
            memory:
                main:
                    strategy: 'cosine'
                products:
                    strategy: 'manhattan'
            chroma_db:
                main:
                    collection: 'documents'

From the configuration above, the following aliases are automatically registered:

- ``StoreInterface $main`` - References the memory store (first occurrence)
- ``StoreInterface $memoryMain`` - Explicitly references the memory store
- ``StoreInterface $chromaDbMain`` - Explicitly references the chroma_db store
- ``StoreInterface $products`` - References the memory products store
- ``StoreInterface $memoryProducts`` - Explicitly references the memory products store

You can inject stores into your services using the generated aliases::

    use Symfony\AI\Store\StoreInterface;

    final readonly class DocumentService
    {
        public function __construct(
            private StoreInterface $main,              // Uses memory store (first occurrence)
            private StoreInterface $chromaDbMain,      // Explicitly uses chroma_db store
            private StoreInterface $memoryProducts,    // Explicitly uses memory products store
        ) {
        }
    }

When multiple stores share the same name (like ``main`` in the example), the simple alias (``$main``) will reference the first occurrence.
Use type-prefixed aliases (``$memoryMain``, ``$chromaDbMain``) for explicit disambiguation.

Model Configuration
-------------------

Models can be configured in two different ways to specify model options and parameters. You can append query parameters directly to the model name using a URL-like syntax:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini?temperature=0.7&max_tokens=2000&stream=true'

Alternatively, you can specify model options in a separate ``options`` section:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model:
                    name: 'gpt-4o-mini'
                    options:
                        temperature: 0.7
                        max_tokens: 2000
                        stream: true

.. note::

    You cannot use both query parameters in the model name and the ``options`` key simultaneously.

You can also define models for the vectorizer this way:

.. code-block:: yaml

    ai:
        vectorizer:
            embeddings:
                model: 'text-embedding-3-small?dimensions=512&encoding_format=float'

HTTP Client Configuration
-------------------------

Each platform can be configured with a custom HTTP client service to handle API requests.
This allows you to customize timeouts, proxy settings, SSL configurations, and other HTTP-specific options.

By default, all platforms use the standard Symfony HTTP client service (``http_client``):

.. code-block:: yaml

    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
                # http_client: 'http_client'  # This is the default

You can specify a custom HTTP client service for any platform:

.. code-block:: yaml

    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
                http_client: 'app.custom_http_client'

System Prompt Configuration
---------------------------

For basic usage, specify the system prompt as a simple string:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini'
                prompt: 'You are a helpful assistant.'

Advanced Configuration
~~~~~~~~~~~~~~~~~~~~~~

For more control, such as including tool definitions in the system prompt, use the array format:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'You are a helpful assistant that can answer questions.'
                    include_tools: true # Include tool definitions at the end of the system prompt

The array format supports these options:

* ``text`` (string): The system prompt text that will be sent to the AI model (either ``text`` or ``file`` is required)
* ``file`` (string): Path to a file containing the system prompt (either ``text`` or ``file`` is required)
* ``include_tools`` (boolean, optional): When set to ``true``, tool definitions will be appended to the system prompt
* ``enable_translation`` (boolean, optional): When set to ``true``, enables translation for the system prompt text (requires symfony/translation)
* ``translation_domain`` (string, optional): The translation domain to use for the system prompt translation

.. note::

    You cannot use both ``text`` and ``file`` simultaneously. Choose one option based on your needs.

File-Based Prompts
~~~~~~~~~~~~~~~~~~

For better organization and reusability, you can store system prompts in external files. This is particularly useful for:

* Long, complex prompts with multiple sections
* Prompts shared across multiple agents or projects
* Version-controlled prompt templates
* JSON-structured prompts with specific formatting

Configure the prompt with a file path:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini'
                prompt:
                    file: '%kernel.project_dir%/prompts/assistant.txt'

The file can be in any text format (.txt, .json, .md, etc.). The entire content of the file will be used as the system prompt text.

Example Text File
.................

``prompts/assistant.txt``:

.. code-block:: text

    You are a helpful and knowledgeable assistant.

    Guidelines:
    - Be clear and direct in your responses
    - Provide examples when appropriate
    - Be respectful and professional at all times

Example JSON File
.................

``prompts/code-reviewer.json``:

.. code-block:: json

    {
      "role": "You are an expert code reviewer",
      "responsibilities": [
        "Review code for bugs and potential issues",
        "Suggest improvements for code quality"
      ],
      "tone": "constructive and educational"
    }

Translation Support
~~~~~~~~~~~~~~~~~~~

To use translated system prompts, you need to have the Symfony Translation component installed:

.. code-block:: terminal

    $ composer require symfony/translation

Then configure the prompt with translation enabled:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'agent.system_prompt'  # Translation key
                    enable_translation: true
                    translation_domain: 'ai_prompts'  # Optional: specify translation domain

The system prompt text will be automatically translated using the configured translator service.
If no translation domain is specified, the default domain will be used.

Memory Provider Configuration
-----------------------------

Memory providers allow agents to access and utilize conversation history and context from previous interactions.
This enables agents to maintain context across conversations and provide more personalized responses.

Static Memory (Simple)
~~~~~~~~~~~~~~~~~~~~~~

The simplest way to add memory is to provide a string that will be used as static context:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini'
                memory: 'You have access to user preferences and conversation history'
                prompt:
                    text: 'You are a helpful assistant.'

This static memory content is consistently available to the agent across all conversations.

Dynamic Memory (Advanced)
~~~~~~~~~~~~~~~~~~~~~~~~~

For more sophisticated scenarios, you can reference an existing service that implements dynamic memory.
Use the array syntax with a ``service`` key to explicitly reference a service:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini'
                memory:
                    service: 'my_memory_service'  # Explicitly references an existing service
                prompt:
                    text: 'You are a helpful assistant.'

Memory as System Prompt
~~~~~~~~~~~~~~~~~~~~~~~

Memory can work independently or alongside the system prompt:

- **Memory only**: If no prompt is provided, memory becomes the system prompt
- **Memory + Prompt**: If both are provided, memory is prepended to the prompt

.. code-block:: yaml

    ai:
        agent:
            # Agent with memory only (memory becomes system prompt)
            memory_only_agent:
                model: 'gpt-4o-mini'
                memory: 'You are a helpful assistant with conversation history'

            # Agent with both memory and prompt (memory prepended to prompt)
            memory_and_prompt_agent:
                model: 'gpt-4o-mini'
                memory: 'Previous conversation context...'
                prompt:
                    text: 'You are a helpful assistant.'

Custom Memory Provider Requirements
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When using a service reference, the memory service must implement the
:class:`Symfony\\AI\\Agent\\Memory\\MemoryProviderInterface`::

    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\Memory\Memory;
    use Symfony\AI\Agent\Memory\MemoryProviderInterface;

    final class MyMemoryProvider implements MemoryProviderInterface
    {
        public function loadMemory(Input $input): array
        {
            // Return an array of Memory objects containing relevant conversation history
            return [
                new Memory('Username: OskarStark'),
                new Memory('Age: 40'),
                new Memory('User preferences: prefers concise answers'),
            ];
        }
    }

Multi-Agent Orchestration
-------------------------

The AI Bundle provides a configuration system for creating multi-agent orchestrators that route requests to specialized agents based on defined handoff rules.

Multi-Agent vs Agent-as-Tool
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The AI Bundle supports two different approaches for combining multiple agents:

1. **Agent-as-Tool**: An agent can use another agent as a tool during its processing. The main agent decides when and how to call the secondary agent, similar to any other tool. This is useful when:

   - The main agent needs optional access to specialized capabilities
   - The decision to use the secondary agent is context-dependent
   - You want the main agent to control the entire conversation flow
   - The secondary agent provides supplementary information

   Example: A general assistant that can optionally query a research agent for detailed information.

2. **Multi-Agent Orchestration**: A dedicated orchestrator analyzes each request and routes it to the most appropriate specialized agent. This is useful when:

   - You have distinct domains that require different expertise
   - You want clear separation of concerns between agents
   - The routing decision should be made upfront based on the request type
   - Each agent should handle the entire conversation for its domain

   Example: A customer service system that routes to technical support, billing, or general inquiries based on the user's question.

Key Differences
^^^^^^^^^^^^^^^

* **Control Flow**: Agent-as-tool maintains control in the primary agent; Multi-Agent delegates full control to the selected agent
* **Decision Making**: Agent-as-tool decides during processing; Multi-Agent decides before processing
* **Response Generation**: Agent-as-tool integrates tool responses; Multi-Agent returns the selected agent's complete response
* **Use Case**: Agent-as-tool for augmentation; Multi-Agent for specialization

Configuration
^^^^^^^^^^^^^

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        multi_agent:
            # Define named multi-agent systems
            support:
                # The main orchestrator agent that analyzes requests
                orchestrator: 'orchestrator'

                # Handoff rules mapping agents to trigger keywords
                # At least 1 handoff required
                handoffs:
                    technical: ['bug', 'problem', 'technical', 'error', 'code', 'debug']

                # Fallback agent for unmatched requests (required)
                fallback: 'general'

Each multi-agent configuration automatically registers a service with the ID pattern ``ai.multi_agent.{name}``.

For the example above, the service ``ai.multi_agent.support`` is registered and can be injected::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\Component\DependencyInjection\Attribute\Autowire;

    final class SupportController
    {
        public function __construct(
            #[Autowire(service: 'ai.multi_agent.support')]
            private AgentInterface $supportAgent,
        ) {
        }

        public function askSupport(string $question): string
        {
            $messages = new MessageBag(Message::ofUser($question));
            $response = $this->supportAgent->call($messages);

            return $response->getContent();
        }
    }

Handoff Rules and Fallback
^^^^^^^^^^^^^^^^^^^^^^^^^^

Handoff rules are defined as a key-value mapping where:

* **Key**: The name of the target agent (automatically prefixed with ``ai.agent.``)
* **Value**: An array of keywords or phrases that trigger this handoff

Example of creating a Handoff in PHP::

    use Symfony\AI\Agent\MultiAgent\Handoff;

    $technicalHandoff = new Handoff(
        to: $technicalAgent,
        when: ['code', 'debug', 'implementation', 'refactor', 'programming']
    );

The ``fallback`` parameter (required) specifies an agent to handle requests that don't match any handoff rules. This ensures all requests have a proper handler.

How It Works
^^^^^^^^^^^^

1. The orchestrator agent receives the initial request
2. It analyzes the request content and matches it against handoff rules
3. If keywords match a handoff's conditions, the request is delegated to that agent
4. If no specific conditions match, the request is delegated to the fallback agent
5. The selected agent processes the request and returns the response

Example: Customer Service Bot
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: yaml

    ai:
        multi_agent:
            customer_service:
                orchestrator: 'analyzer'
                handoffs:
                    tech_support: ['error', 'bug', 'crash', 'not working', 'broken']
                    billing: ['payment', 'invoice', 'billing', 'subscription', 'price']
                    product_info: ['features', 'how to', 'tutorial', 'guide', 'documentation']
                fallback: 'general_support'  # Fallback for general inquiries

Commands
--------

The AI Bundle provides several console commands for interacting with AI platforms, agents, and stores.

``ai:platform:invoke``
~~~~~~~~~~~~~~~~~~~~~~

The ``ai:platform:invoke`` command allows you to directly invoke any configured AI platform with a message.
This is useful for testing platform configurations and quick interactions with AI models.

.. code-block:: terminal

    $ php bin/console ai:platform:invoke <platform> <model> "<message>"

    # Using OpenAI
    $ php bin/console ai:platform:invoke openai gpt-4o-mini "Hello, world!"

    # Using Anthropic
    $ php bin/console ai:platform:invoke anthropic claude-3-5-sonnet-20241022 "Explain quantum physics"

``ai:agent:call``
~~~~~~~~~~~~~~~~~

The ``ai:agent:call`` command provides an interactive chat interface to communicate with configured agents.
This is useful for testing agent configurations, tools, and conversational flows.

.. code-block:: terminal

    $ php bin/console ai:agent:call <agent>

    # Chat with the default agent
    $ php bin/console ai:agent:call default

    # Chat with a specific agent
    $ php bin/console ai:agent:call wikipedia

The command starts an interactive session where you can:

* Type messages and press Enter to send them to the agent
* See the agent's responses in real-time
* View the system prompt that was configured for the agent
* Type ``exit`` or ``quit`` to end the conversation

If no agent name is provided, you'll be prompted to select one from the available configured agents.

``ai:store:setup``
~~~~~~~~~~~~~~~~~~

The ``ai:store:setup`` command prepares the required infrastructure for a store (e.g., creates database tables, indexes, collections).

.. code-block:: terminal

    $ php bin/console ai:store:setup <store>

    # Setup the chroma_db store
    $ php bin/console ai:store:setup chroma_db.default

.. note::

    This command only works with stores that implement ``ManagedStoreInterface``.
    Not all store types support or require setup operations.

``ai:store:drop``
~~~~~~~~~~~~~~~~~

The ``ai:store:drop`` command drops the infrastructure for a store (e.g., removes database tables, indexes, collections).

.. code-block:: terminal

    $ php bin/console ai:store:drop <store> --force

    # Drop the chroma_db store
    $ php bin/console ai:store:drop chroma_db.default --force

.. warning::

    The ``--force`` (or ``-f``) option is required to prevent accidental data loss.
    This command will permanently delete all data in the store.

.. note::

    This command only works with stores that implement ``ManagedStoreInterface``.
    Not all store types support drop operations.

``ai:store:index``
~~~~~~~~~~~~~~~~~~

The ``ai:store:index`` command indexes documents into a store using a configured indexer.

.. code-block:: terminal

    $ php bin/console ai:store:index <indexer>

    # Index using the default indexer
    $ php bin/console ai:store:index default

    # Override the configured source with a single file
    $ php bin/console ai:store:index blog --source=/path/to/file.txt

    # Override with multiple sources
    $ php bin/console ai:store:index blog --source=/path/to/file1.txt --source=/path/to/file2.txt

The ``--source`` (or ``-s``) option allows you to override the source(s) configured in your indexer.
This is useful for ad-hoc indexing operations or testing different data sources.

Usage
-----

Agent Service
~~~~~~~~~~~~~

Use the :class:`Symfony\\AI\\Agent\\Agent` service to leverage models and tools::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    final readonly class MyService
    {
        public function __construct(
            private AgentInterface $agent,
        ) {
        }

        public function submit(string $message): string
        {
            $messages = new MessageBag(
                Message::forSystem('Speak like a pirate.'),
                Message::ofUser($message),
            );

            return $this->agent->call($messages);
        }
    }

Register Processors
~~~~~~~~~~~~~~~~~~~

By default, all services implementing the :class:`Symfony\\AI\\Agent\\InputProcessorInterface` or the
:class:`Symfony\\AI\\Agent\\OutputProcessorInterface` interfaces are automatically applied to every :class:`Symfony\\AI\\Agent\\Agent`.

This behavior can be overridden/configured with the :class:`Symfony\\AI\\AiBundle\\Attribute\\AsInputProcessor` and
the :class:`Symfony\\AI\\AiBundle\\Attribute\\AsOutputProcessor` attributes::

    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\InputProcessorInterface;
    use Symfony\AI\Agent\Output;
    use Symfony\AI\Agent\OutputProcessorInterface;

    #[AsInputProcessor(priority: 99)] // This applies to every agent
    #[AsOutputProcessor(agent: 'ai.agent.my_agent_name')] // The output processor will only be registered for 'ai.agent.my_agent_name'
    final readonly class MyService implements InputProcessorInterface, OutputProcessorInterface
    {
        public function processInput(Input $input): void
        {
            // ...
        }

        public function processOutput(Output $output): void
        {
            // ...
        }
    }

Register Tools
~~~~~~~~~~~~~~

To use existing tools, you can register them as a service:

.. code-block:: yaml

    services:
        _defaults:
            autowire: true
            autoconfigure: true

        Symfony\AI\Agent\Toolbox\Tool\Clock: ~
        Symfony\AI\Agent\Toolbox\Tool\OpenMeteo: ~
        Symfony\AI\Agent\Toolbox\Tool\SerpApi:
            $apiKey: '%env(SERP_API_KEY)%'
        Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch: ~
        Symfony\AI\Agent\Toolbox\Tool\Tavily:
          $apiKey: '%env(TAVILY_API_KEY)%'
        Symfony\AI\Agent\Toolbox\Tool\Wikipedia: ~
        Symfony\AI\Agent\Toolbox\Tool\YouTubeTranscriber: ~
        Symfony\AI\Agent\Toolbox\Tool\Firecrawl:
          $endpoint: '%env(FIRECRAWL_ENDPOINT)%'
          $apiKey: '%env(FIRECRAWL_API_KEY)%'
        Symfony\AI\Agent\Toolbox\Tool\Brave:
          $apiKey: '%env(BRAVE_API_KEY)%'

Custom tools can be registered by using the :class:`Symfony\\AI\\Agent\\Toolbox\\Attribute\\AsTool` attribute::

    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

    #[AsTool('company_name', 'Provides the name of your company')]
    final class CompanyName
    {
        public function __invoke(): string
        {
            return 'ACME Corp.';
        }
    }

The agent configuration by default will inject all known tools into the agent.

To disable this behavior, set the ``tools`` option to ``false``:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                tools: false

To inject only specific tools, list them in the configuration:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                tools:
                    - 'Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch'

To restrict the access to a tool, you can use the :class:`Symfony\\AI\\Agent\\Attribute\\IsGrantedTool` attribute, which
works similar to :class:`Symfony\\Component\\Security\\Http\\Attribute\\IsGranted` attribute in `symfony/security-http`. For this to work,
make sure you have `symfony/security-core` installed in your project.

::

    use Symfony\AI\Agent\Attribute\IsGrantedTool;

    #[IsGrantedTool('ROLE_ADMIN')]
    #[AsTool('company_name', 'Provides the name of your company')]
    final class CompanyName
    {
        public function __invoke(): string
        {
            return 'ACME Corp.';
        }
    }

The attribute :class:`Symfony\\AI\\Agent\\Attribute\\IsGrantedTool` can be added on class- or method-level - even multiple
times. If multiple attributes apply to one tool call, a logical AND is used and all access
decisions have to grant access.

Token Usage Tracking
--------------------

Token usage tracking is a feature provided by some of the Platform's bridges, for monitoring and analyzing the
consumption of tokens by your agents. This feature is particularly useful for understanding costs and performance.

When enabled, the agent will automatically track token usage information and add it
to the result metadata. The tracked information includes:

* **Prompt tokens**: Number of tokens used in the input/prompt
* **Completion tokens**: Number of tokens generated in the response
* **Total tokens**: Total number of tokens used (prompt + completion)
* **Remaining tokens**: Number of remaining tokens in rate limits (when available)
* **Cached tokens**: Number of cached tokens used (when available)
* **Thinking tokens**: Number of reasoning tokens used (for models that support reasoning)

The token usage information can be accessed from the result metadata::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\Result\Metadata\TokenUsage\TokenUsage;

    final readonly class MyService
    {
        public function __construct(
            private AgentInterface $agent,
        ) {
        }

        public function getTokenUsage(string $message): ?TokenUsage
        {
            $messages = new MessageBag(Message::ofUser($message));
            $result = $this->agent->call($messages);

            return $result->getMetadata()->get('token_usage');
        }
    }

Disable Tracking
~~~~~~~~~~~~~~~~

To disable token usage tracking for an agent, set the ``track_token_usage`` option to ``false``:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                model: 'gpt-4o-mini'
                track_token_usage: false

Vectorizers
-----------

Vectorizers are components that convert text documents into vector embeddings for storage and retrieval.
They can be configured once and reused across multiple indexers, providing better maintainability and consistency.

Configuring Vectorizers
~~~~~~~~~~~~~~~~~~~~~~~

Vectorizers are defined in the ``vectorizer`` section of your configuration:

.. code-block:: yaml

    ai:
        vectorizer:
            openai_small:
                platform: 'ai.platform.openai'
                model:
                    name: 'text-embedding-3-small'
                    options:
                        dimensions: 512

            openai_large:
                platform: 'ai.platform.openai'
                model: 'text-embedding-3-large'

            mistral_embed:
                platform: 'ai.platform.mistral'
                model: 'mistral-embed'

Using Vectorizers in Indexers
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Once configured, vectorizers can be referenced by name in indexer configurations:

.. code-block:: yaml

    ai:
        indexer:
            documents:
                loader: 'Symfony\AI\Store\Document\Loader\TextFileLoader'
                vectorizer: 'ai.vectorizer.openai_small'
                store: 'ai.store.chroma_db.documents'

            research:
                loader: 'Symfony\AI\Store\Document\Loader\TextFileLoader'
                vectorizer: 'ai.vectorizer.openai_large'
                store: 'ai.store.chroma_db.research'

            knowledge_base:
                loader: 'Symfony\AI\Store\Document\Loader\InMemoryLoader'
                vectorizer: 'ai.vectorizer.mistral_embed'
                store: 'ai.store.memory.kb'

Benefits of Configured Vectorizers
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

* **Reusability**: Define once, use in multiple indexers
* **Consistency**: Ensure all indexers using the same vectorizer have identical embedding configuration
* **Maintainability**: Change vectorizer settings in one place

Profiler
--------

The profiler panel provides insights into the agent's execution:

.. image:: images/profiler-ai.png
   :alt: Profiler Panel

Message stores
--------------

Message stores are critical to store messages sent to agents in the short / long term, they can be configured
and reused in multiple chats, providing the capacity to agents to keep previous interactions.

Configuring message stores
~~~~~~~~~~~~~~~~~~~~~~~~~~

Message stores are defined in the ``message_store`` section of your configuration:

.. code-block:: yaml

    ai:
        # ...
        message_store:
            youtube:
                cache:
                    service: 'cache.app'
                    key: 'youtube'

Chats
-----

Chats are the entrypoint when it comes to sending messages to agents and retrieving content (mostly text)
that contains the response from the agent.

Each chat requires to define an agent and a message store.

Configuring Chats
~~~~~~~~~~~~~~~~~

Chats are defined in the ``chat`` section of your configuration:

.. code-block:: yaml

    ai:
        # ...
        chat:
            youtube:
                agent: 'ai.agent.youtube'
                message_store: 'ai.message_store.cache.youtube'

.. _`Symfony AI Agent`: https://github.com/symfony/ai-agent
.. _`Symfony AI Chat`: https://github.com/symfony/ai-chat
.. _`Symfony AI Platform`: https://github.com/symfony/ai-platform
.. _`Symfony AI Store`: https://github.com/symfony/ai-store
