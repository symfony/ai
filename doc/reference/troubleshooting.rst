Troubleshooting Guide
=====================

This guide helps you diagnose and resolve common issues when working with Symfony AI.

Common Issues
-------------

Installation Problems
~~~~~~~~~~~~~~~~~~~~~

**Problem: Composer memory errors during installation**

.. code-block:: text

    Fatal error: Allowed memory size of 1610612736 bytes exhausted

**Solution:**

.. code-block:: terminal

    # Increase PHP memory limit
    $ php -d memory_limit=-1 composer require symfony/ai-bundle
    
    # Or permanently in php.ini
    memory_limit = 2G

**Problem: Version conflicts with Symfony components**

.. code-block:: text

    Your requirements could not be resolved to an installable set of packages

**Solution:**

.. code-block:: terminal

    # Update Symfony components first
    $ composer update symfony/*
    
    # Then install AI bundle
    $ composer require symfony/ai-bundle
    
    # Or specify version constraints
    $ composer require "symfony/ai-bundle:^1.0"

API Connection Issues
~~~~~~~~~~~~~~~~~~~~~

**Problem: SSL certificate verification failed**

.. code-block:: text

    cURL error 60: SSL certificate problem: unable to get local issuer certificate

**Solution:**

.. code-block:: php

    // Development only - add to config/packages/dev/framework.yaml
    framework:
        http_client:
            default_options:
                verify_peer: false
                verify_host: false

    // Production - update CA certificates
    // Ubuntu/Debian:
    $ sudo apt-get update && sudo apt-get install ca-certificates
    
    // macOS:
    $ brew install ca-certificates

**Problem: Connection timeout to AI provider**

.. code-block:: text

    Symfony\Component\HttpClient\Exception\TransportException: Connection timeout

**Solution:**

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        http_client:
            timeout: 60  # Increase timeout
            max_retries: 3
            retry_delay: 1000

Authentication Errors
~~~~~~~~~~~~~~~~~~~~~

**Problem: Invalid API key**

.. code-block:: text

    401 Unauthorized: Invalid API key provided

**Solution:**

1. Check environment variable is set:

.. code-block:: terminal

    $ symfony var:export | grep OPENAI_API_KEY
    
    # Or in .env.local
    $ cat .env.local | grep OPENAI_API_KEY

2. Verify key format:

.. code-block:: bash

    # OpenAI keys start with 'sk-'
    OPENAI_API_KEY=sk-...
    
    # Anthropic keys start with 'sk-ant-'
    ANTHROPIC_API_KEY=sk-ant-...

3. Clear cache after changing environment variables:

.. code-block:: terminal

    $ symfony console cache:clear

Model and Provider Issues
--------------------------

Rate Limiting
~~~~~~~~~~~~~

**Problem: Rate limit exceeded**

.. code-block:: text

    429 Too Many Requests: Rate limit reached

**Solution:**

1. Implement exponential backoff:

.. code-block:: php

    use Symfony\Component\HttpClient\RetryableHttpClient;
    use Symfony\Component\HttpClient\Retry\ExponentialBackoffStrategy;

    $strategy = new ExponentialBackoffStrategy(
        delayMs: 1000,
        multiplier: 2,
        maxDelayMs: 10000
    );
    
    $httpClient = new RetryableHttpClient($httpClient, $strategy, 3);

2. Add application-level rate limiting:

.. code-block:: yaml

    framework:
        rate_limiter:
            ai_requests:
                policy: 'token_bucket'
                limit: 50
                rate: { interval: '1 minute' }

Token Limits
~~~~~~~~~~~~

**Problem: Maximum context length exceeded**

.. code-block:: text

    400 Bad Request: This model's maximum context length is 8192 tokens

**Solution:**

1. Reduce message history:

.. code-block:: php

    // Keep only last N messages
    $messages = $messageHistory->slice(-10);

2. Truncate long content:

.. code-block:: php

    $content = mb_substr($content, 0, 5000);

3. Use model with larger context:

.. code-block:: php

    // Switch to model with larger context window
    $model = new Gpt(Gpt::GPT_4_TURBO); // 128k context

Content Filtering
~~~~~~~~~~~~~~~~~

**Problem: Content blocked by safety filters**

.. code-block:: text

    ContentFilterException: The response was filtered due to content policy

**Solution:**

1. Review and adjust prompts:

.. code-block:: php

    // Add safety instructions to system prompt
    Message::forSystem(
        'You are a helpful assistant. Always provide safe, 
         appropriate responses suitable for all audiences.'
    )

2. Handle content filter exceptions:

.. code-block:: php

    try {
        $result = $agent->call($messages);
    } catch (ContentFilterException $e) {
        // Provide alternative response
        return "I cannot provide a response to that request.";
    }

Tool Calling Issues
-------------------

Tool Not Found
~~~~~~~~~~~~~~

**Problem: Tool not found or not registered**

.. code-block:: text

    ToolNotFoundException: Tool "my_tool" not found

**Solution:**

1. Ensure tool is registered:

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Tool\MyTool:
            tags: ['ai.tool']

2. Check tool attribute:

.. code-block:: php

    #[AsTool('my_tool', 'Tool description')]
    class MyTool
    {
        public function __invoke(): string
        {
            return 'result';
        }
    }

Tool Execution Errors
~~~~~~~~~~~~~~~~~~~~~

**Problem: Tool throws exception during execution**

**Solution:**

Use fault-tolerant toolbox:

.. code-block:: php

    use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;

    $toolbox = new FaultTolerantToolbox($innerToolbox);
    $processor = new AgentProcessor($toolbox);

Tool Parameter Issues
~~~~~~~~~~~~~~~~~~~~~

**Problem: Invalid tool parameters from AI**

**Solution:**

Add parameter validation:

.. code-block:: php

    use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

    #[AsTool('search', 'Search tool')]
    class SearchTool
    {
        public function __invoke(
            #[With(minLength: 1, maxLength: 100)]
            string $query,
            #[With(minimum: 1, maximum: 50)]
            int $limit = 10
        ): array {
            // Validate parameters
            if (empty($query)) {
                throw new \InvalidArgumentException('Query cannot be empty');
            }
            
            return $this->search($query, $limit);
        }
    }

Vector Store Issues
-------------------

Dimension Mismatch
~~~~~~~~~~~~~~~~~~

**Problem: Vector dimension mismatch**

.. code-block:: text

    InvalidArgumentException: Vector dimension 1536 does not match store dimension 768

**Solution:**

Ensure embeddings model matches store configuration:

.. code-block:: yaml

    ai:
        indexer:
            default:
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Embeddings'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Embeddings::TEXT_3_SMALL
                    # TEXT_3_SMALL produces 1536 dimensions
        
        store:
            mariadb:
                default:
                    dimensions: 1536  # Must match embedding dimensions

Connection Issues
~~~~~~~~~~~~~~~~~

**Problem: Cannot connect to vector database**

**Solution:**

1. Check database credentials:

.. code-block:: bash

    DATABASE_URL=mysql://user:password@localhost:3306/dbname

2. Verify database extensions:

.. code-block:: sql

    -- PostgreSQL: Check pgvector
    SELECT * FROM pg_extension WHERE extname = 'vector';
    
    -- If not installed:
    CREATE EXTENSION vector;

3. Initialize store schema:

.. code-block:: php

    if ($store instanceof InitializableStoreInterface) {
        $store->initialize();
    }

Performance Issues
------------------

Slow Response Times
~~~~~~~~~~~~~~~~~~~

**Problem: AI responses are very slow**

**Solution:**

1. Use streaming for better perceived performance:

.. code-block:: php

    $result = $agent->call($messages, ['stream' => true]);
    foreach ($result->getContent() as $chunk) {
        echo $chunk;
        flush();
    }

2. Cache frequently used responses:

.. code-block:: php

    $cacheKey = md5(json_encode($messages));
    if ($cache->hasItem($cacheKey)) {
        return $cache->getItem($cacheKey)->get();
    }

3. Use faster models for simple tasks:

.. code-block:: php

    // Use GPT-3.5 for simple queries
    $model = new Gpt(Gpt::GPT_35_TURBO);

Memory Issues
~~~~~~~~~~~~~

**Problem: PHP memory exhausted with large documents**

**Solution:**

1. Process documents in chunks:

.. code-block:: php

    $transformer = new TextSplitTransformer(
        maxLength: 500,
        overlap: 50
    );
    
    foreach ($transformer->transform($document) as $chunk) {
        $indexer->index($chunk);
    }

2. Use generators for large datasets:

.. code-block:: php

    function processDocuments(): \Generator
    {
        foreach ($files as $file) {
            yield new TextDocument(file_get_contents($file));
        }
    }

Debugging
---------

Enable Debug Mode
~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/dev/ai.yaml
    ai:
        debug: true
        profiler:
            enabled: true
            collect_requests: true
            collect_responses: true

Use Profiler
~~~~~~~~~~~~

1. Check the Symfony toolbar for AI panel
2. Review request/response details
3. Monitor token usage
4. Track tool executions

Logging
~~~~~~~

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

.. code-block:: php

    use Psr\Log\LoggerInterface;

    class ChatService
    {
        public function __construct(
            private LoggerInterface $aiLogger
        ) {}
        
        public function chat(string $message): string
        {
            $this->aiLogger->info('Chat request', ['message' => $message]);
            
            try {
                $response = $this->agent->call($messages);
                $this->aiLogger->info('Chat response', [
                    'response' => $response->getContent()
                ]);
                return $response;
            } catch (\Exception $e) {
                $this->aiLogger->error('Chat error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
    }

Testing Issues
--------------

Mock Platform Not Working
~~~~~~~~~~~~~~~~~~~~~~~~~~

**Problem: Tests fail with real API calls**

**Solution:**

Properly mock the platform:

.. code-block:: php

    use Symfony\AI\Platform\InMemoryPlatform;

    class ChatTest extends KernelTestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            
            // Replace real platform with mock
            $platform = new InMemoryPlatform('Test response');
            self::getContainer()->set('ai.platform.openai', $platform);
        }
    }

Test Data Fixtures
~~~~~~~~~~~~~~~~~~

Create consistent test data:

.. code-block:: php

    class AITestFixtures
    {
        public static function createTestMessages(): MessageBag
        {
            return new MessageBag(
                Message::forSystem('Test system prompt'),
                Message::ofUser('Test user message')
            );
        }
        
        public static function createTestVector(): Vector
        {
            return new Vector(array_fill(0, 1536, 0.1));
        }
    }

Getting Help
------------

Resources
~~~~~~~~~

1. **Documentation**: Read the full documentation
2. **Examples**: Check ``examples/`` directory
3. **Demo App**: Run the demo application
4. **GitHub Issues**: https://github.com/symfony/ai/issues
5. **Symfony Slack**: #ai channel

Reporting Issues
~~~~~~~~~~~~~~~~

When reporting issues, include:

1. Symfony AI version: ``composer show symfony/ai-*``
2. PHP version: ``php -v``
3. Error messages and stack traces
4. Minimal code to reproduce
5. Configuration files (without API keys)

Common Error Codes
~~~~~~~~~~~~~~~~~~

* **400**: Bad request - Check input parameters
* **401**: Unauthorized - Verify API key
* **403**: Forbidden - Check permissions
* **404**: Not found - Verify endpoint/model name
* **429**: Rate limited - Implement backoff
* **500**: Server error - Retry with backoff
* **503**: Service unavailable - Provider issue

Next Steps
----------

* Review security best practices: :doc:`../resources/security`
* Optimize performance: :doc:`../resources/performance`
* Check examples: :doc:`../resources/examples`