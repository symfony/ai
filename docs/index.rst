Symfony AI Documentation
========================

Symfony AI is a set of components that integrate AI capabilities into PHP applications,
providing a unified interface to work with various AI platforms like OpenAI, Anthropic,
Google Gemini, Azure, and more.

Getting Started
---------------

Symfony AI consists of several components and bundles that work together to bring
AI capabilities to your application:

* :doc:`Platform Component </components/platform>`: Unified interface to various AI models and providers
* :doc:`Agent Component </components/agent>`: Framework for building AI agents with tools and workflows
* :doc:`Chat Component </components/chat>`: API to interact with agents and store conversation history
* :doc:`Store Component </components/store>`: Data storage abstraction for vector databases and RAG applications
* :doc:`Mate Component </components/mate>`: MCP server for AI assistant integration with your application
* :doc:`AI Bundle </bundles/ai-bundle>`: Symfony integration bringing all components together
* :doc:`MCP Bundle </bundles/mcp-bundle>`: Integration for the Model Context Protocol SDK

Quick Start
-----------

Install the AI Bundle to get started with Symfony AI:

.. code-block:: terminal

    $ composer require symfony/ai-bundle

Configure your AI platform:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        agent:
            default:
                model: 'gpt-4o-mini'

Use the agent in your service::

    namespace App\Agent;

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    final readonly class ChatService
    {
        public function __construct(
            private AgentInterface $agent,
        ) {
        }

        public function chat(string $userMessage): string
        {
            $messages = new MessageBag(
                Message::forSystem('You are a helpful assistant.'),
                Message::ofUser($userMessage),
            );

            $result = $this->agent->call($messages);

            return $result->getContent();
        }
    }

Key Features
------------

**Multi-Platform Support**
    Connect to OpenAI, Anthropic Claude, Google Gemini, Azure OpenAI, AWS Bedrock,
    Mistral, Ollama, and many more platforms with a unified interface.

**Tool Calling**
    Extend your agents with custom tools to interact with external APIs, databases,
    and services. Built-in tools available for common tasks.

**Retrieval Augmented Generation (RAG)**
    Build context-aware applications using vector stores with support for
    ChromaDB, Pinecone, Weaviate, MongoDB Atlas, and more.

**Structured Output**
    Extract structured data from unstructured text using PHP classes or array schemas.

**Multi-Modal Support**
    Process text, images, audio, and other content types with compatible models.

**Streaming Support**
    Stream responses from AI models for real-time user experiences.

**Memory Management**
    Add contextual memory to agents for personalized conversations.

**Testing Tools**
    Mock agents and platforms for reliable testing without external API calls.

Documentation
-------------

.. toctree::
    :maxdepth: 2

    components/index
    bundles/index
    cookbook/index
