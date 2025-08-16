AI Bundle
=========

The AI Bundle provides seamless Symfony integration for all AI components, including dependency injection, 
configuration management, developer tools, and profiler integration.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-bundle

The bundle is automatically registered with Symfony Flex. For manual registration::

    <?php

    // config/bundles.php
    return [
        // ...
        Symfony\AI\Bundle\AiBundle::class => ['all' => true],
    ];

Features
--------

The AI Bundle provides:

* **Service Configuration**: Automatic service registration and configuration
* **Dependency Injection**: Full integration with Symfony's service container
* **Developer Tools**: Profiler panel and debug toolbar integration
* **Tool Discovery**: Automatic discovery of tools with ``#[AsTool]`` attribute
* **Security Integration**: Tool authorization with ``#[IsGrantedTool]``
* **Configuration Validation**: Validates configuration at compile time
* **Environment Management**: Secure API key handling through environment variables

Service Registration
--------------------

Automatic Service Discovery
~~~~~~~~~~~~~~~~~~~~~~~~~~~

The bundle automatically discovers and registers:

1. **Tools** with ``#[AsTool]`` attribute
2. **Platforms** configured in ``ai.platform``
3. **Agents** configured in ``ai.agent``
4. **Stores** configured in ``ai.store``

.. code-block:: php

    <?php
    namespace App\Tool;

    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

    #[AsTool('my_tool', 'Tool description')]
    class MyTool
    {
        // Automatically registered and available to agents
        public function __invoke(): string
        {
            return 'result';
        }
    }

Service Injection
~~~~~~~~~~~~~~~~~

Inject AI services into your classes::

    <?php

    <?php
    namespace App\Service;

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\PlatformInterface;
    use Symfony\AI\Store\StoreInterface;

    class MyService
    {
        public function __construct(
            // Inject default agent
            private AgentInterface $agent,
            
            // Inject specific platform
            private PlatformInterface $platform,
            
            // Inject specific store
            private StoreInterface $store
        ) {}
    }

Named Service Injection
~~~~~~~~~~~~~~~~~~~~~~~

Inject specific named services:

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Service\ChatService:
            arguments:
                # Inject specific agent
                $agent: '@ai.agent.chatbot'
                
                # Inject specific platform
                $platform: '@ai.platform.openai'
                
                # Inject specific store
                $store: '@ai.store.mariadb.default'

Configuration
-------------

Basic Configuration
~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        # Configure platforms
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        
        # Configure agents
        agent:
            default:
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: 'gpt-4o-mini'

Multi-Provider Setup
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
            
            anthropic:
                api_key: '%env(ANTHROPIC_API_KEY)%'
            
            azure:
                gpt:
                    base_url: '%env(AZURE_ENDPOINT)%'
                    deployment: '%env(AZURE_DEPLOYMENT)%'
                    api_key: '%env(AZURE_KEY)%'
        
        agent:
            # Agent using OpenAI
            chatbot:
                platform: 'ai.platform.openai'
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: 'gpt-4o'
            
            # Agent using Anthropic
            research:
                platform: 'ai.platform.anthropic'
                model:
                    class: 'Symfony\AI\Platform\Bridge\Anthropic\Claude'
                    name: 'claude-3-sonnet'

Tool Configuration
~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    ai:
        agent:
            default:
                # Tool configuration options
                tools: 
                    # Include all tools (default)
                    - all
                    
                    # Or specific tools only
                    - '@App\Tool\WeatherTool'
                    - 'Symfony\AI\Agent\Toolbox\Tool\Clock'
                    
                    # Or exclude all tools
                    - false
                
                # Include tool definitions in system prompt
                include_tools: true
                
                # Enable fault tolerant toolbox
                fault_tolerant_toolbox: true

Developer Tools
---------------

Profiler Integration
~~~~~~~~~~~~~~~~~~~~

The bundle adds an AI panel to the Symfony Profiler showing:

* **Request Details**: Model, messages, options
* **Response Details**: Content, token usage, timing
* **Tool Executions**: Tools called, parameters, results
* **Performance Metrics**: API latency, token counts
* **Cost Estimation**: Estimated API costs

.. image:: profiler.png
   :alt: AI Profiler Panel

Enable profiler in development:

.. code-block:: yaml

    # config/packages/dev/ai.yaml
    ai:
        profiler:
            enabled: true
            collect_requests: true
            collect_responses: true
            collect_tokens: true

Debug Toolbar
~~~~~~~~~~~~~

The debug toolbar shows:

* Number of AI requests
* Total tokens used
* Total execution time
* Number of tool calls

Logging
~~~~~~~

Configure AI-specific logging:

.. code-block:: yaml

    # config/packages/monolog.yaml
    monolog:
        channels: ['ai']
        handlers:
            ai:
                type: stream
                path: '%kernel.logs_dir%/ai.log'
                level: debug
                channels: ['ai']

Security Features
-----------------

Tool Authorization
~~~~~~~~~~~~~~~~~~

Control tool access with ``#[IsGrantedTool]``::

    <?php

    use Symfony\AI\Bundle\Security\Attribute\IsGrantedTool;
    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

    #[IsGrantedTool('ROLE_ADMIN')]
    #[AsTool('admin_tool', 'Administrative tool')]
    class AdminTool
    {
        public function __invoke(): string
        {
            // Only accessible by users with ROLE_ADMIN
            return 'admin data';
        }
    }

Multiple authorization checks::

    <?php

    #[IsGrantedTool('ROLE_USER')]
    #[AsTool('user_tool', 'User tool')]
    class UserTool
    {
        #[IsGrantedTool('ROLE_PREMIUM')]
        public function premiumFeature(): string
        {
            // Requires both ROLE_USER (class) and ROLE_PREMIUM (method)
            return 'premium content';
        }
    }

API Key Security
~~~~~~~~~~~~~~~~

Use Symfony secrets for API keys:

.. code-block:: terminal

    # Create secret
    $ php bin/console secrets:set OPENAI_API_KEY
    
    # List secrets
    $ php bin/console secrets:list
    
    # Deploy secrets
    $ php bin/console secrets:decrypt-to-local --env=prod

Service Tags
------------

Available Service Tags
~~~~~~~~~~~~~~~~~~~~~~

The bundle uses these tags for service configuration:

* ``ai.tool``: Register a tool
* ``ai.input_processor``: Register an input processor
* ``ai.output_processor``: Register an output processor
* ``ai.memory_provider``: Register a memory provider

Manual Tool Registration
~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    services:
        App\Tool\CustomTool:
            tags:
                - { name: 'ai.tool', tool_name: 'custom_tool' }

Processor Registration
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    services:
        App\Processor\CustomProcessor:
            tags:
                - 'ai.input_processor'
                - 'ai.output_processor'

Commands
--------

The bundle provides console commands:

List Available Models
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ php bin/console ai:models:list
    
    Available Models:
    - openai.gpt-4o
    - openai.gpt-4o-mini
    - anthropic.claude-3-sonnet
    ...

Test Agent
~~~~~~~~~~

.. code-block:: terminal

    $ php bin/console ai:agent:test default "Hello, how are you?"
    
    Response: I'm doing well, thank you! How can I help you today?

Index Documents
~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ php bin/console ai:store:index documents/*.txt
    
    Indexed 10 documents successfully

Events
------

The bundle dispatches these events:

Agent Events
~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Bundle\Event\AgentCallEvent;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;

    class AgentEventSubscriber implements EventSubscriberInterface
    {
        public static function getSubscribedEvents(): array
        {
            return [
                AgentCallEvent::class => 'onAgentCall',
            ];
        }
        
        public function onAgentCall(AgentCallEvent $event): void
        {
            // Log agent calls
            $messages = $event->getMessages();
            $options = $event->getOptions();
        }
    }

Tool Events
~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;

    class ToolEventListener
    {
        public function onToolCallsExecuted(ToolCallsExecuted $event): void
        {
            foreach ($event->toolCallResults as $result) {
                // Process tool results
                $toolName = $result->toolCall->name;
                $toolResult = $result->result;
            }
        }
    }

Testing Support
---------------

Test Configuration
~~~~~~~~~~~~~~~~~~

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

Test Helpers
~~~~~~~~~~~~

.. code-block:: php

    use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
    use Symfony\AI\Agent\AgentInterface;

    class AgentTest extends KernelTestCase
    {
        public function testAgent(): void
        {
            self::bootKernel();
            
            $agent = self::getContainer()->get(AgentInterface::class);
            $result = $agent->call($messages);
            
            $this->assertEquals('Test response', $result->getContent());
        }
    }

Performance
-----------

Caching
~~~~~~~

Enable caching for better performance:

.. code-block:: yaml

    framework:
        cache:
            pools:
                ai.cache:
                    adapter: cache.adapter.redis
    
    ai:
        cache:
            pool: 'ai.cache'
            ttl: 3600

Connection Pooling
~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    ai:
        http_client:
            max_connections: 10
            timeout: 30

Lazy Loading
~~~~~~~~~~~~

Services are lazy-loaded for better performance::

    <?php

    // Agent is only instantiated when first used
    class MyService
    {
        public function __construct(
            private AgentInterface $agent
        ) {
            // Agent not instantiated yet
        }
        
        public function doSomething(): void
        {
            // Agent instantiated on first use
            $this->agent->call($messages);
        }
    }

Bundle Extension
----------------

Create custom bundle extensions::

    <?php

    <?php
    namespace App\DependencyInjection;

    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Extension\Extension;

    class AppAIExtension extends Extension
    {
        public function load(array $configs, ContainerBuilder $container): void
        {
            // Register custom services
            $container->register('app.custom_tool', CustomTool::class)
                ->addTag('ai.tool');
        }
    }

Compiler Passes
~~~~~~~~~~~~~~~

Add custom compiler passes::

    <?php

    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;

    class CustomToolPass implements CompilerPassInterface
    {
        public function process(ContainerBuilder $container): void
        {
            // Process tool services
            $taggedServices = $container->findTaggedServiceIds('ai.tool');
            
            foreach ($taggedServices as $id => $tags) {
                // Custom processing
            }
        }
    }

Migration Guide
---------------

From Standalone Components
~~~~~~~~~~~~~~~~~~~~~~~~~~~

If migrating from standalone component usage:

1. Install the bundle
2. Move configuration to ``config/packages/ai.yaml``
3. Replace manual service creation with dependency injection
4. Update tool registration to use attributes
5. Remove manual platform initialization

Before::

    <?php

    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
    $agent = new Agent($platform, $model);

After::

    <?php

    public function __construct(
        private AgentInterface $agent
    ) {}

Next Steps
----------

* Configure your first agent: :doc:`../reference/configuration`
* Build a chatbot: :doc:`../guides/building-chatbot`
* Explore tools: :doc:`../features/tool-calling`
* Learn about security: :doc:`../resources/security`