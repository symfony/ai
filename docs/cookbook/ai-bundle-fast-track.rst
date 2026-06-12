.. card:
    title: AI Bundle Fast Track
    description: Wire platforms, agents, tools, and a vector store in a Symfony app with YAML.
    icon: package
    components: Platform, Agent, Store

AI Bundle Fast Track
====================

The :doc:`AI Bundle </bundles/ai-bundle>` is the Symfony integration for the Platform, Agent,
Store, and Chat components. Instead of wiring services by hand, you describe platforms, agents,
tools, and stores in ``config/packages/ai.yaml`` and the bundle registers everything in the
container for you. This guide is a fast track from an empty configuration to an agent that
answers questions over your own documents. Each step links to a dedicated cookbook article when
you want to go deeper.

Prerequisites
-------------

* A Symfony application
* An API key for at least one AI platform (e.g. ``OPENAI_API_KEY``)

Step 1: Install the Bundle
--------------------------

.. code-block:: terminal

    $ composer require symfony/ai-bundle

With Symfony Flex the bundle is registered automatically and an empty
``config/packages/ai.yaml`` is created.

Step 2: Configure One or More Platforms
---------------------------------------

A *platform* is the connection to an AI provider. Define each provider under the ``platform``
key; every entry becomes a service named ``ai.platform.<name>``:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'

You can configure several providers side by side and pick one per agent later:

.. code-block:: yaml

    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
            anthropic:
                api_key: '%env(ANTHROPIC_API_KEY)%'
            gemini:
                api_key: '%env(GEMINI_API_KEY)%'

This registers ``ai.platform.openai``, ``ai.platform.anthropic``, and ``ai.platform.gemini``.
See the :doc:`AI Bundle reference </bundles/ai-bundle>` for the full list of supported providers
(Azure, Bedrock, VertexAI, Ollama, Perplexity, and more) and for the cached and generic platform
decorators.

Step 3: Configure an Agent
--------------------------

An *agent* combines a platform, a model, and optionally tools and a system prompt. The simplest
agent only needs a model — it uses the first configured platform by default:

.. code-block:: yaml

    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        agent:
            default:
                model: 'gpt-4o-mini'

Each agent is registered as a service. With a single agent you can inject
:class:`Symfony\\AI\\Agent\\AgentInterface` directly::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    final readonly class AssistantService
    {
        public function __construct(
            private AgentInterface $agent,
        ) {
        }

        public function ask(string $question): string
        {
            $messages = new MessageBag(Message::ofUser($question));

            return $this->agent->call($messages)->getContent();
        }
    }

When you define multiple agents, point each one at the platform it should use and inject a
specific one with the ``ai.agent.<name>`` service id:

.. code-block:: yaml

    ai:
        agent:
            assistant:
                platform: 'ai.platform.openai'
                model: 'gpt-4o-mini'
            researcher:
                platform: 'ai.platform.anthropic'
                model: 'claude-3-7-sonnet-latest'

.. code-block:: php

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\Component\DependencyInjection\Attribute\Autowire;

    public function __construct(
        #[Autowire(service: 'ai.agent.researcher')]
        private AgentInterface $researcher,
    ) {
    }

You can also chat with any configured agent straight from the console:

.. code-block:: terminal

    $ php bin/console ai:agent:call assistant

Step 4: Add a System Prompt
---------------------------

The system prompt shapes how the agent behaves. For simple cases pass a string:

.. code-block:: yaml

    ai:
        agent:
            assistant:
                model: 'gpt-4o-mini'
                prompt: 'You are a concise assistant for a Symfony application.'

Use the array form for more control — for example, to append the available tool definitions to
the prompt, or to load a long prompt from a file:

.. code-block:: yaml

    ai:
        agent:
            assistant:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'You are a concise assistant for a Symfony application.'
                    include_tools: true
            reviewer:
                model: 'gpt-4o-mini'
                prompt:
                    file: '%kernel.project_dir%/prompts/reviewer.md'

The array form also supports translated prompts via ``enable_translation`` and
``translation_domain``. See the :doc:`AI Bundle reference </bundles/ai-bundle>` for all prompt
options.

Step 5: Give the Agent Tools
----------------------------

Tools let an agent call your PHP code. Any service carrying the
:class:`Symfony\\AI\\Agent\\Toolbox\\Attribute\\AsTool` attribute is auto-registered, and by
default every known tool is injected into every agent::

    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

    #[AsTool('company_name', 'Provides the name of your company')]
    final class CompanyName
    {
        public function __invoke(): string
        {
            return 'ACME Corp.';
        }
    }

To control which tools an agent receives, list them explicitly — or set ``tools: false`` to
disable tools entirely:

.. code-block:: yaml

    ai:
        agent:
            assistant:
                model: 'gpt-4o-mini'
                tools:
                    - 'Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch'

Several built-in tools ship as standalone packages with Flex recipes, so installing them is
enough to make them available:

.. code-block:: terminal

    $ composer require symfony/ai-brave-tool
    $ composer require symfony/ai-wikipedia-tool
    $ composer require symfony/ai-open-meteo-tool

For writing and securing your own tools, see :doc:`tool-calling-with-agents`,
:doc:`dynamic-tools`, and :doc:`human-in-the-loop`.

Step 6: Configure a Store, Vectorizer, and Indexer
--------------------------------------------------

To let the agent answer over your own data, you need a vector *store*, a *vectorizer* that turns
text into embeddings, and an *indexer* that fills the store. All three are configured in YAML:

.. code-block:: yaml

    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'

        store:
            chromadb:
                knowledge_base:
                    collection: 'docs'

        vectorizer:
            embeddings:
                platform: 'ai.platform.openai'
                model: 'text-embedding-3-small'

        indexer:
            docs:
                loader: 'Symfony\AI\Store\Document\Loader\TextFileLoader'
                vectorizer: 'ai.vectorizer.embeddings'
                store: 'ai.store.chromadb.knowledge_base'

For local experiments you can swap ChromaDB for the in-memory store. Prepare the store
infrastructure and run the indexer from the console:

.. code-block:: terminal

    $ php bin/console ai:store:setup chromadb.knowledge_base
    $ php bin/console ai:store:index docs --source=/path/to/document.txt

An indexer without a ``loader`` becomes a ``DocumentIndexer`` that you feed documents directly in
PHP by injecting :class:`Symfony\\AI\\Store\\IndexerInterface`. See :doc:`rag-implementation` for
the full indexing pipeline, chunking, and metadata handling.

Step 7: Search the Store with the Agent
---------------------------------------

The :class:`Symfony\\AI\\Agent\\Bridge\\SimilaritySearch\\SimilaritySearch` tool runs a semantic
search over a store and feeds the matching documents back to the model. It needs a *retriever*,
which pairs a vectorizer with a store:

.. code-block:: yaml

    ai:
        retriever:
            docs:
                vectorizer: 'ai.vectorizer.embeddings'
                store: 'ai.store.chromadb.knowledge_base'

        agent:
            assistant:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'Answer questions using only the SimilaritySearch tool. If you cannot find relevant information, say so.'
                tools:
                    - 'Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch'

    services:
        Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch:
            $retriever: '@ai.retriever.docs'

Now calling the agent with a user question triggers a similarity search automatically, and the
answer is grounded in your indexed documents. You can also use a retriever on its own by
injecting :class:`Symfony\\AI\\Store\\RetrieverInterface` for plain semantic search without an
agent.

Putting It Together
-------------------

A complete fast-track configuration looks like this:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'

        store:
            chromadb:
                knowledge_base:
                    collection: 'docs'

        vectorizer:
            embeddings:
                platform: 'ai.platform.openai'
                model: 'text-embedding-3-small'

        indexer:
            docs:
                loader: 'Symfony\AI\Store\Document\Loader\TextFileLoader'
                vectorizer: 'ai.vectorizer.embeddings'
                store: 'ai.store.chromadb.knowledge_base'

        retriever:
            docs:
                vectorizer: 'ai.vectorizer.embeddings'
                store: 'ai.store.chromadb.knowledge_base'

        agent:
            assistant:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'Answer questions using only the SimilaritySearch tool. If you cannot find relevant information, say so.'
                tools:
                    - 'Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch'

From here you can grow in any direction: add more platforms and agents, route between them with
multi-agent orchestration, persist conversations with message stores and chats, or expose your
tools over MCP.

Learn More
----------

* :doc:`../bundles/ai-bundle` - Full AI Bundle configuration reference
* :doc:`tool-calling-with-agents` - Let agents call your PHP functions
* :doc:`rag-implementation` - Build a complete RAG pipeline
* :doc:`multi-agent-orchestration` - Route requests to specialist agents
* :doc:`chatbot-with-memory` - Persist conversation history
* :doc:`build-an-mcp-server` - Expose your tools over MCP
