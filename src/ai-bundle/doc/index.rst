AI Bundle
=========

Symfony integration bundle for Symfony AI components.

Integrating:

* `Symfony AI Agent`_
* `Symfony AI Platform`_
* `Symfony AI Store`_

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-bundle

Configuration
-------------

**Simple Example with OpenAI**

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        agent:
            default:
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Gpt::GPT_4O_MINI

**Advanced Example with Anthropic, Azure, ElevenLabs, Gemini, Perplexity, Vertex AI, Ollama multiple agents**

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
                structured_output: false # Disables support for "output_structure" option, default is true
                track_token_usage: true # Enable tracking of token usage for the agent, default is true
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Gpt::GPT_4O_MINI
                system_prompt: 'You are a helpful assistant that can answer questions.' # The default system prompt of the agent
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
                model:
                    class: 'Symfony\AI\Platform\Bridge\Anthropic\Claude'
                    name: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_37
                tools: # If undefined, all tools are injected into the agent, use "tools: false" to disable tools.
                    - 'Symfony\AI\Agent\Toolbox\Tool\Wikipedia'
                fault_tolerant_toolbox: false # Disables fault tolerant toolbox, default is true
            search_agent:
                platform: 'ai.platform.perplexity'
                model:
                    class: 'Symfony\AI\Platform\Bridge\Perplexity\Perplexity'
                    name: !php/const Symfony\AI\Platform\Bridge\Perplexity\Perplexity::SONAR
                tools: false
            audio:
                platform: 'ai.platform.eleven_labs'
                model:
                    class: 'Symfony\AI\Platform\Bridge\ElevenLabs'
                    name: !php/const Symfony\AI\Platform\Bridge\ElevenLabs::TEXT_TO_SPEECH
                tools: false
        store:
            # also azure_search, meilisearch, memory, mongodb, pinecone, qdrant and surrealdb are supported as store type
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
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Embeddings'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Embeddings::TEXT_EMBEDDING_3_SMALL
                    options:
                        dimensions: 512
            mistral_embeddings:
                platform: 'ai.platform.mistral'
                model:
                    class: 'Symfony\AI\Platform\Bridge\Mistral\Embeddings'
                    name: !php/const Symfony\AI\Platform\Bridge\Mistral\Embeddings::MISTRAL_EMBED
        indexer:
            default:
                vectorizer: 'ai.vectorizer.openai_embeddings'
                store: 'ai.store.chroma_db.default'

            research:
                vectorizer: 'ai.vectorizer.mistral_embeddings'
                store: 'ai.store.memory.research'

Usage
-----

**Agent Service**

Use the `Agent` service to leverage models and tools::

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

**Register Processors**

By default, all services implementing the ``InputProcessorInterface`` or the
``OutputProcessorInterface`` interfaces are automatically applied to every ``Agent``.

This behavior can be overridden/configured with the ``#[AsInputProcessor]`` and
the ``#[AsOutputProcessor]`` attributes::

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

**Register Tools**

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

Custom tools can be registered by using the ``#[AsTool]`` attribute::

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

To restrict the access to a tool, you can use the ``IsGrantedTool`` attribute, which
works similar to ``IsGranted`` attribute in `symfony/security-http`. For this to work,
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
The attribute ``IsGrantedTool`` can be added on class- or method-level - even multiple
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

**Supported Platforms**

Token usage tracking is currently supported, and by default enabled, for the following platforms:

* **OpenAI**: Tracks all token types including cached and thinking tokens
* **Mistral**: Tracks basic token usage and rate limit information

**Disable Tracking**

To disable token usage tracking for an agent, set the ``track_token_usage`` option to ``false``:

.. code-block:: yaml

    ai:
        agent:
            my_agent:
                track_token_usage: false
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Gpt::GPT_4O_MINI

Vectorizers
-----------

Vectorizers are components that convert text documents into vector embeddings for storage and retrieval.
They can be configured once and reused across multiple indexers, providing better maintainability and consistency.

**Configuring Vectorizers**

Vectorizers are defined in the ``vectorizer`` section of your configuration:

.. code-block:: yaml

    ai:
        vectorizer:
            openai_small:
                platform: 'ai.platform.openai'
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Embeddings'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Embeddings::TEXT_EMBEDDING_3_SMALL
                    options:
                        dimensions: 512

            openai_large:
                platform: 'ai.platform.openai'
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Embeddings'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Embeddings::TEXT_EMBEDDING_3_LARGE

            mistral_embed:
                platform: 'ai.platform.mistral'
                model:
                    class: 'Symfony\AI\Platform\Bridge\Mistral\Embeddings'
                    name: !php/const Symfony\AI\Platform\Bridge\Mistral\Embeddings::MISTRAL_EMBED

**Using Vectorizers in Indexers**

Once configured, vectorizers can be referenced by name in indexer configurations:

.. code-block:: yaml

    ai:
        indexer:
            documents:
                vectorizer: 'ai.vectorizer.openai_small'
                store: 'ai.store.chroma_db.documents'

            research:
                vectorizer: 'ai.vectorizer.openai_large'
                store: 'ai.store.chroma_db.research'

            knowledge_base:
                vectorizer: 'ai.vectorizer.mistral_embed'
                store: 'ai.store.memory.kb'

**Benefits of Configured Vectorizers**

* **Reusability**: Define once, use in multiple indexers
* **Consistency**: Ensure all indexers using the same vectorizer have identical embedding configuration
* **Maintainability**: Change vectorizer settings in one place

Profiler
--------

The profiler panel provides insights into the agent's execution:

.. image:: profiler.png
   :alt: Profiler Panel


.. _`Symfony AI Agent`: https://github.com/symfony/ai-agent
.. _`Symfony AI Platform`: https://github.com/symfony/ai-platform
.. _`Symfony AI Store`: https://github.com/symfony/ai-store
