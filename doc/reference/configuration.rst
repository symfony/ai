Configuration Reference
=======================

This document provides a complete reference for configuring Symfony AI components through YAML configuration 
files and environment variables.

Full Configuration Example
--------------------------

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        # Platform configurations
        platform:
            # OpenAI configuration
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
                organization: '%env(OPENAI_ORG_ID)%'  # Optional
                base_url: 'https://api.openai.com/v1' # Optional, custom endpoint

            # Anthropic configuration
            anthropic:
                api_key: '%env(ANTHROPIC_API_KEY)%'
                version: '2023-06-01'  # Optional API version

            # Azure OpenAI configuration (multiple deployments supported)
            azure:
                gpt_deployment:
                    base_url: '%env(AZURE_OPENAI_ENDPOINT)%'
                    deployment: '%env(AZURE_GPT_DEPLOYMENT)%'
                    api_key: '%env(AZURE_OPENAI_KEY)%'
                    api_version: '2024-02-15-preview'
                
                embeddings_deployment:
                    base_url: '%env(AZURE_OPENAI_ENDPOINT)%'
                    deployment: '%env(AZURE_EMBEDDINGS_DEPLOYMENT)%'
                    api_key: '%env(AZURE_OPENAI_KEY)%'
                    api_version: '2024-02-15-preview'

            # Google Gemini configuration
            gemini:
                api_key: '%env(GEMINI_API_KEY)%'
                project_id: '%env(GEMINI_PROJECT_ID)%'  # Optional

            # AWS Bedrock configuration
            bedrock:
                region: '%env(AWS_REGION)%'
                access_key_id: '%env(AWS_ACCESS_KEY_ID)%'
                secret_access_key: '%env(AWS_SECRET_ACCESS_KEY)%'
                session_token: '%env(AWS_SESSION_TOKEN)%'  # Optional

            # Mistral configuration
            mistral:
                api_key: '%env(MISTRAL_API_KEY)%'
                endpoint: 'https://api.mistral.ai'  # Optional

            # Ollama configuration (local models)
            ollama:
                host_url: '%env(OLLAMA_HOST)%'  # Default: http://localhost:11434

            # HuggingFace configuration
            huggingface:
                api_key: '%env(HUGGINGFACE_API_KEY)%'
                endpoint: 'https://api-inference.huggingface.co'  # Optional

        # Agent configurations
        agent:
            # Default agent
            default:
                platform: 'ai.platform.openai'  # Reference to platform service
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Gpt::GPT_4O_MINI
                    options:                    # Model-specific default options
                        temperature: 0.7
                        max_tokens: 2000
                
                system_prompt: 'You are a helpful assistant.'  # Default system prompt
                include_tools: true              # Include tool definitions in system prompt
                structured_output: true          # Enable structured output support
                fault_tolerant_toolbox: true     # Enable fault tolerant toolbox
                
                tools:                          # Tool configuration
                    # All tools (default if tools is not specified)
                    - all
                    
                    # Or specific tools by service ID
                    - '@App\Tool\WeatherTool'
                    - 'Symfony\AI\Agent\Toolbox\Tool\Clock'
                    
                    # Or configure inline
                    - service: 'App\Tool\CustomTool'
                      name: 'custom_tool'
                      description: 'Custom tool description'
                      method: 'execute'  # Default: __invoke
                    
                    # Or reference another agent
                    - agent: 'research_agent'
                      name: 'research'
                      description: 'Research assistant'

            # Specialized agent for RAG
            rag_agent:
                platform: 'ai.platform.openai'
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: 'gpt-4o'
                system_prompt: |
                    You are a knowledge assistant. Answer questions using only
                    the similarity_search tool. If you cannot find relevant
                    information, clearly state that.
                tools:
                    - 'Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch'

            # Research agent with different model
            research_agent:
                platform: 'ai.platform.anthropic'
                model:
                    class: 'Symfony\AI\Platform\Bridge\Anthropic\Claude'
                    name: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_37
                tools:
                    - 'Symfony\AI\Agent\Toolbox\Tool\Wikipedia'
                    - 'Symfony\AI\Agent\Toolbox\Tool\Tavily'

        # Store configurations
        store:
            # MariaDB vector store
            mariadb:
                default:
                    dsn: '%env(DATABASE_URL)%'
                    table: 'vector_embeddings'
                    dimensions: 1536
                    distance_strategy: 'cosine'  # cosine, euclidean, dot_product

            # MongoDB Atlas vector store
            mongodb:
                default:
                    connection: '%env(MONGODB_URL)%'
                    database: 'ai'
                    collection: 'vectors'
                    index: 'vector_index'

            # PostgreSQL with pgvector
            postgres:
                default:
                    dsn: '%env(DATABASE_URL)%'
                    table: 'embeddings'
                    dimensions: 1536
                    distance_strategy: 'cosine'

            # Pinecone vector store
            pinecone:
                default:
                    api_key: '%env(PINECONE_API_KEY)%'
                    environment: '%env(PINECONE_ENVIRONMENT)%'
                    index: 'production'
                    namespace: 'default'
                    dimensions: 1536

            # Qdrant vector store
            qdrant:
                default:
                    url: '%env(QDRANT_URL)%'
                    api_key: '%env(QDRANT_API_KEY)%'  # Optional
                    collection: 'documents'

            # Meilisearch
            meilisearch:
                default:
                    url: '%env(MEILISEARCH_URL)%'
                    api_key: '%env(MEILISEARCH_API_KEY)%'
                    index: 'vectors'

            # ChromaDB
            chroma_db:
                default:
                    host: '%env(CHROMA_HOST)%'
                    port: 8000
                    collection: 'embeddings'

            # In-memory store (development/testing)
            memory:
                default: ~

            # Cache-based store
            cache:
                default:
                    pool: 'cache.app'  # PSR-6 cache pool service

        # Indexer configurations
        indexer:
            default:
                platform: 'ai.platform.openai'
                store: 'ai.store.mariadb.default'
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Embeddings'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Embeddings::TEXT_3_SMALL
                
                # Document processing options
                chunk_size: 500
                chunk_overlap: 50
                batch_size: 100

Environment Variables
---------------------

Common Environment Variables
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    # .env.local

    # OpenAI
    OPENAI_API_KEY=sk-...
    OPENAI_ORG_ID=org-...  # Optional

    # Anthropic
    ANTHROPIC_API_KEY=sk-ant-api03-...

    # Azure OpenAI
    AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
    AZURE_OPENAI_KEY=...
    AZURE_GPT_DEPLOYMENT=gpt-4o
    AZURE_EMBEDDINGS_DEPLOYMENT=text-embedding-3-small
    AZURE_OPENAI_VERSION=2024-02-15-preview

    # Google Gemini
    GEMINI_API_KEY=...
    GEMINI_PROJECT_ID=...  # Optional

    # AWS Bedrock
    AWS_REGION=us-east-1
    AWS_ACCESS_KEY_ID=...
    AWS_SECRET_ACCESS_KEY=...
    AWS_SESSION_TOKEN=...  # Optional for temporary credentials

    # Mistral
    MISTRAL_API_KEY=...

    # Ollama (local)
    OLLAMA_HOST=http://localhost:11434

    # HuggingFace
    HUGGINGFACE_API_KEY=...

    # Vector Stores
    DATABASE_URL=mysql://user:pass@localhost:3306/mydb
    MONGODB_URL=mongodb://localhost:27017
    PINECONE_API_KEY=...
    PINECONE_ENVIRONMENT=us-east-1
    QDRANT_URL=http://localhost:6333
    MEILISEARCH_URL=http://localhost:7700
    MEILISEARCH_API_KEY=...

    # Tool APIs
    SERP_API_KEY=...
    TAVILY_API_KEY=...
    FIRECRAWL_API_KEY=...
    FIRECRAWL_ENDPOINT=https://api.firecrawl.dev

Service References
------------------

Platform Services
~~~~~~~~~~~~~~~~~

Platforms are available as services with the naming pattern ``ai.platform.{name}``:

.. code-block:: yaml

    services:
        App\Service\MyService:
            arguments:
                $platform: '@ai.platform.openai'
                $anthropicPlatform: '@ai.platform.anthropic'
                $azureGpt: '@ai.platform.azure.gpt_deployment'

Agent Services
~~~~~~~~~~~~~~

Agents are available as services with the naming pattern ``ai.agent.{name}``:

.. code-block:: yaml

    services:
        App\Controller\ChatController:
            arguments:
                $agent: '@ai.agent.default'
                $ragAgent: '@ai.agent.rag_agent'

    # Or inject the default agent interface
    services:
        App\Service\ChatService:
            arguments:
                $agent: '@Symfony\AI\Agent\AgentInterface'

Store Services
~~~~~~~~~~~~~~

Stores are available as services with the naming pattern ``ai.store.{type}.{name}``:

.. code-block:: yaml

    services:
        App\Service\SearchService:
            arguments:
                $store: '@ai.store.mariadb.default'
                $mongoStore: '@ai.store.mongodb.default'

Advanced Configuration
----------------------

Custom Platform Factory
~~~~~~~~~~~~~~~~~~~~~~~

Register a custom platform factory:

.. code-block:: yaml

    services:
        app.custom_platform:
            class: Symfony\AI\Platform\Platform
            factory: ['App\AI\CustomPlatformFactory', 'create']
            arguments:
                - '%env(CUSTOM_API_KEY)%'
        
        ai.platform.custom:
            alias: app.custom_platform
            public: true

Custom Tool Registration
~~~~~~~~~~~~~~~~~~~~~~~~

Register custom tools with automatic discovery:

.. code-block:: yaml

    services:
        # Auto-register all tools in a directory
        App\Tool\:
            resource: '../src/Tool/'
            tags: ['ai.tool']
        
        # Manual tool registration
        App\Tool\CustomTool:
            tags:
                - { name: 'ai.tool', tool_name: 'custom_tool' }

Memory Provider Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Configure memory providers for agents:

.. code-block:: yaml

    services:
        app.static_memory:
            class: Symfony\AI\Agent\Memory\StaticMemoryProvider
            arguments:
                - 'User context fact 1'
                - 'User context fact 2'
        
        app.embedding_memory:
            class: Symfony\AI\Agent\Memory\EmbeddingProvider
            arguments:
                $platform: '@ai.platform.openai'
                $embeddings: '@ai.embeddings.default'
                $store: '@ai.store.mariadb.default'

Processor Configuration
~~~~~~~~~~~~~~~~~~~~~~~

Configure custom processors:

.. code-block:: yaml

    services:
        app.translation_processor:
            class: App\AI\TranslationProcessor
            arguments:
                $targetLanguage: 'fr'
            tags: ['ai.input_processor']
        
        app.profanity_filter:
            class: App\AI\ProfanityFilterProcessor
            tags: ['ai.output_processor']

Performance Tuning
------------------

Connection Pooling
~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    ai:
        http_client:
            max_connections: 10
            timeout: 30
            max_retries: 3
            retry_delay: 1000  # milliseconds

Caching Configuration
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    framework:
        cache:
            pools:
                ai.embeddings:
                    adapter: cache.adapter.redis
                    default_lifetime: 86400  # 24 hours
                
                ai.responses:
                    adapter: cache.adapter.filesystem
                    default_lifetime: 3600   # 1 hour

Rate Limiting
~~~~~~~~~~~~~

.. code-block:: yaml

    ai:
        rate_limiting:
            openai:
                requests_per_minute: 60
                tokens_per_minute: 90000
            
            anthropic:
                requests_per_minute: 50
                tokens_per_minute: 100000

Profiler Configuration
----------------------

Enable AI profiling in development:

.. code-block:: yaml

    # config/packages/dev/ai.yaml
    ai:
        profiler:
            enabled: true
            collect_requests: true
            collect_responses: true
            collect_tokens: true
            collect_tools: true

Security Configuration
----------------------

API Key Encryption
~~~~~~~~~~~~~~~~~~

Use Symfony secrets for API keys:

.. code-block:: terminal

    $ php bin/console secrets:set OPENAI_API_KEY
    $ php bin/console secrets:set ANTHROPIC_API_KEY

Access Control
~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/security.yaml
    security:
        access_control:
            - { path: ^/ai/admin, roles: ROLE_ADMIN }
            - { path: ^/ai/chat, roles: ROLE_USER }

Tool Security
~~~~~~~~~~~~~

.. code-block:: yaml

    ai:
        security:
            tools:
                require_authentication: true
                allowed_roles: ['ROLE_USER']
                audit_log: true

Testing Configuration
---------------------

Test Environment
~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/test/ai.yaml
    ai:
        platform:
            test:
                class: Symfony\AI\Platform\InMemoryPlatform
                response: 'Test response'
        
        agent:
            default:
                platform: 'ai.platform.test'
                model:
                    class: 'Symfony\AI\Platform\Model'
                    name: 'test-model'
        
        store:
            memory:
                default: ~

Validation Rules
----------------

Configuration validation ensures:

1. Required API keys are present
2. Model classes exist and are valid
3. Service references are valid
4. Dimensions match between embeddings and stores
5. Tool services implement required interfaces

Next Steps
----------

* Configure specific providers: :doc:`../providers/openai`
* Set up agents: :doc:`../components/agent`
* Configure stores: :doc:`../stores/overview`
* Learn about security: :doc:`../resources/security`