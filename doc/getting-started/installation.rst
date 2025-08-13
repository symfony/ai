Installation
============

This guide covers the installation and initial setup of Symfony AI components in your project.

Requirements
------------

* PHP 8.2 or higher
* Composer 2.0 or higher
* Symfony 6.4 or 7.0+ (for bundle integration)
* Extensions required by specific features:
  * ``ext-curl`` for API communications
  * ``ext-pdo`` for MariaDB/PostgreSQL vector stores
  * ``ext-mongodb`` for MongoDB vector store (optional)

Installing Components
---------------------

Symfony AI is modular - install only the components you need:

Full Bundle Installation (Recommended)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

For Symfony applications, install the AI Bundle which includes all core components:

.. code-block:: terminal

    $ composer require symfony/ai-bundle

This installs:

* ``symfony/ai-platform`` - Core platform abstraction
* ``symfony/ai-agent`` - Agent framework
* ``symfony/ai-store`` - Vector store abstraction
* Bundle integration with Symfony

Individual Components
~~~~~~~~~~~~~~~~~~~~~

Install components separately for non-Symfony projects or specific needs:

.. code-block:: terminal

    # Core platform component (required for most features)
    $ composer require symfony/ai-platform
    
    # Agent framework (for building AI agents)
    $ composer require symfony/ai-agent
    
    # Vector stores (for RAG and semantic search)
    $ composer require symfony/ai-store
    
    # MCP SDK (for Model Context Protocol)
    $ composer require symfony/mcp-sdk
    
    # MCP Bundle (Symfony integration for MCP)
    $ composer require symfony/mcp-bundle

Provider-Specific Dependencies
-------------------------------

Some AI providers require additional packages:

OpenAI & Azure OpenAI
~~~~~~~~~~~~~~~~~~~~~

No additional dependencies required - HTTP client is included.

Anthropic Claude
~~~~~~~~~~~~~~~~

No additional dependencies required for direct API access.

For AWS Bedrock:

.. code-block:: terminal

    $ composer require aws/aws-sdk-php

Google Gemini
~~~~~~~~~~~~~

No additional dependencies required.

Ollama (Local Models)
~~~~~~~~~~~~~~~~~~~~~

Requires Ollama server running locally. Install from https://ollama.com

HuggingFace
~~~~~~~~~~~

No additional dependencies for API access.

For local inference with Transformers PHP:

.. code-block:: terminal

    $ composer require codewithkyrian/transformers

Vector Store Dependencies
--------------------------

Install additional packages based on your chosen vector store:

.. code-block:: terminal

    # MongoDB Atlas
    $ composer require mongodb/mongodb
    
    # Pinecone
    $ composer require probots-io/pinecone-php
    
    # ChromaDB
    $ composer require codewithkyrian/chromadb-php
    
    # Meilisearch
    $ composer require meilisearch/meilisearch-php
    
    # Qdrant
    $ composer require qdrant/qdrant-php

Basic Configuration
-------------------

For Symfony Applications
~~~~~~~~~~~~~~~~~~~~~~~~

1. The bundle is automatically registered if using Symfony Flex.

2. Create configuration file ``config/packages/ai.yaml``:

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

3. Add your API key to ``.env.local``:

.. code-block:: bash

    OPENAI_API_KEY=your-api-key-here

For Standalone Usage
~~~~~~~~~~~~~~~~~~~~

Initialize components programmatically:

.. code-block:: php

    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Agent\Agent;

    // Create platform
    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
    
    // Create model
    $model = new Gpt(Gpt::GPT_4O_MINI);
    
    // Create agent
    $agent = new Agent($platform, $model);

Environment Variables
---------------------

Common environment variables for different providers:

.. code-block:: bash

    # OpenAI
    OPENAI_API_KEY=sk-...
    
    # Anthropic
    ANTHROPIC_API_KEY=sk-ant-...
    
    # Azure OpenAI
    AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
    AZURE_OPENAI_KEY=...
    AZURE_OPENAI_DEPLOYMENT=your-deployment-name
    AZURE_OPENAI_VERSION=2024-02-15-preview
    
    # Google Gemini
    GEMINI_API_KEY=...
    
    # AWS Bedrock
    AWS_ACCESS_KEY_ID=...
    AWS_SECRET_ACCESS_KEY=...
    AWS_REGION=us-east-1
    
    # Mistral
    MISTRAL_API_KEY=...
    
    # Ollama (local)
    OLLAMA_HOST=http://localhost:11434

Verifying Installation
----------------------

Test your installation with a simple script:

.. code-block:: php

    <?php
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    require 'vendor/autoload.php';

    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
    $model = new Gpt(Gpt::GPT_4O_MINI);

    $messages = new MessageBag(
        Message::ofUser('Hello, AI!')
    );

    $result = $platform->invoke($model, $messages);
    echo $result->getContent(); // Should output a greeting

Docker Setup
------------

For containerized environments, use the provided Docker compose example:

.. code-block:: yaml

    # docker-compose.yml
    services:
        app:
            build: .
            environment:
                OPENAI_API_KEY: ${OPENAI_API_KEY}
                # Add other API keys as needed
        
        # Optional: Local Ollama server
        ollama:
            image: ollama/ollama
            ports:
                - "11434:11434"
            volumes:
                - ollama:/root/.ollama
    
    volumes:
        ollama:

Development Tools
-----------------

Install additional development dependencies:

.. code-block:: terminal

    $ composer require --dev symfony/debug-bundle
    $ composer require --dev symfony/maker-bundle

This enables:

* Profiler integration with AI request tracking
* Debug toolbar showing AI metrics
* Maker commands for generating AI-related code

Next Steps
----------

With Symfony AI installed, you're ready to:

* Follow the :doc:`quick-start` guide for hands-on examples
* Explore :doc:`architecture` to understand the component structure
* Configure specific providers in the :doc:`../providers/index` section
* Build your first AI agent following :doc:`../guides/building-chatbot`

Troubleshooting
---------------

Common installation issues:

**Composer Memory Errors**
    Increase PHP memory limit: ``php -d memory_limit=-1 composer require ...``

**SSL Certificate Errors**
    Update CA certificates or disable SSL verification (development only):
    ``composer config disable-tls true``

**Version Conflicts**
    Ensure you're using compatible Symfony and PHP versions. Check ``composer.json`` requirements.

**Missing Extensions**
    Install required PHP extensions via your package manager or Docker configuration.

For more help, see :doc:`../reference/troubleshooting` or open an issue on GitHub.