Agent Component
===============

The Agent component provides a high-level framework for building AI agents that can interact with users, 
execute tools, manage workflows, and maintain conversation context. It sits on top of the Platform component 
and adds sophisticated orchestration capabilities.

Overview
--------

The Agent component enables you to:

* Build conversational AI agents with tool-calling capabilities
* Manage complex workflows with input/output processors
* Maintain conversation memory and context
* Execute functions and interact with external systems
* Get structured, type-safe responses from AI models
* Handle errors gracefully with fault-tolerant toolboxes

Basic Agent Usage
-----------------

Creating an Agent
~~~~~~~~~~~~~~~~~

::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;

    // Create platform and model
    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
    $model = new Gpt(Gpt::GPT_4O_MINI);

    // Create agent
    $agent = new Agent($platform, $model);

    // Use the agent
    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('What is the weather in Paris?')
    );

    $result = $agent->call($messages);
    echo $result->getContent();

Tool Calling
------------

Tools enable agents to execute functions and interact with your application.

Creating Tools
~~~~~~~~~~~~~~

Define tools using the ``#[AsTool]`` attribute::

    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

    #[AsTool('get_current_time', 'Get the current date and time')]
    class CurrentTimeTool
    {
        public function __invoke(): string
        {
            return (new \DateTime())->format('Y-m-d H:i:s');
        }
    }

    #[AsTool('calculate', 'Perform mathematical calculations')]
    class CalculatorTool
    {
        /**
         * @param float $a First number
         * @param float $b Second number
         * @param string $operation Operation to perform (add, subtract, multiply, divide)
         */
        public function __invoke(float $a, float $b, string $operation): float
        {
            return match($operation) {
                'add' => $a + $b,
                'subtract' => $a - $b,
                'multiply' => $a * $b,
                'divide' => $b !== 0 ? $a / $b : 0,
                default => throw new \InvalidArgumentException("Unknown operation: $operation")
            };
        }
    }

Tool Parameters
~~~~~~~~~~~~~~~

Use ``#[With]`` attribute for parameter validation::

    use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

    #[AsTool('search_products', 'Search for products in the catalog')]
    class ProductSearchTool
    {
        /**
         * @param string $query Search query
         * @param int $limit Maximum results to return
         * @param string $category Product category
         */
        public function __invoke(
            #[With(minLength: 3, maxLength: 100)]
            string $query,
            #[With(minimum: 1, maximum: 50)]
            int $limit = 10,
            #[With(enum: ['electronics', 'clothing', 'books', 'food'])]
            string $category = 'all'
        ): array {
            // Search implementation
            return [
                ['name' => 'Product 1', 'price' => 29.99],
                ['name' => 'Product 2', 'price' => 49.99],
            ];
        }
    }

Using Tools with Agent
~~~~~~~~~~~~~~~~~~~~~~

::

    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;

    // Create tools
    $timeTool = new CurrentTimeTool();
    $calculatorTool = new CalculatorTool();

    // Create toolbox
    $toolbox = Toolbox::create($timeTool, $calculatorTool);
    $processor = new AgentProcessor($toolbox);

    // Create agent with tools
    $agent = new Agent(
        $platform,
        $model,
        inputProcessors: [$processor],
        outputProcessors: [$processor]
    );

    // Ask questions that require tools
    $messages = new MessageBag(
        Message::ofUser('What time is it?')
    );

    $result = $agent->call($messages);
    echo $result->getContent(); // "The current time is 2024-01-15 14:30:00"

Multiple Tool Methods
~~~~~~~~~~~~~~~~~~~~~

One class can provide multiple tools::

    #[AsTool('weather_current', 'Get current weather', method: 'current')]
    #[AsTool('weather_forecast', 'Get weather forecast', method: 'forecast')]
    class WeatherService
    {
        public function current(string $location): array
        {
            return [
                'location' => $location,
                'temperature' => 22,
                'condition' => 'sunny'
            ];
        }

        public function forecast(string $location, int $days = 5): array
        {
            return [
                'location' => $location,
                'forecast' => array_map(
                    fn($day) => ['day' => $day, 'temp' => rand(15, 25)],
                    range(1, $days)
                )
            ];
        }
    }

Fault-Tolerant Toolbox
~~~~~~~~~~~~~~~~~~~~~~

Handle tool errors gracefully::

    use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;

    $innerToolbox = Toolbox::create($tool1, $tool2);
    $faultTolerantToolbox = new FaultTolerantToolbox($innerToolbox);

    $processor = new AgentProcessor($faultTolerantToolbox);

    // Agent will receive error messages instead of exceptions
    $agent = new Agent($platform, $model, [$processor], [$processor]);

Memory Management
-----------------

Add contextual memory to agent conversations.

Static Memory
~~~~~~~~~~~~~

Provide fixed context that's always available::

    use Symfony\AI\Agent\Memory\StaticMemoryProvider;
    use Symfony\AI\Agent\Memory\MemoryInputProcessor;

    $staticMemory = new StaticMemoryProvider(
        'User name is John Doe',
        'User prefers concise answers',
        'User is a software developer',
        'Current project is an e-commerce platform'
    );

    $memoryProcessor = new MemoryInputProcessor($staticMemory);

    $agent = new Agent($platform, $model, [$memoryProcessor]);

    // Memory is automatically included in context
    $messages = new MessageBag(
        Message::ofUser('What should I work on today?')
    );

    $result = $agent->call($messages);
    // Response considers the user's context

Embedding-Based Memory
~~~~~~~~~~~~~~~~~~~~~~

Retrieve relevant context based on similarity::

    use Symfony\AI\Agent\Memory\EmbeddingProvider;
    use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

    $embeddings = new Embeddings(Embeddings::TEXT_3_SMALL);
    $store = new InMemoryStore();

    // Index some knowledge
    $indexer = new Indexer($platform, $embeddings, $store);
    $indexer->index(new TextDocument('The company was founded in 2020'));
    $indexer->index(new TextDocument('Our main product is CloudSync'));

    // Create embedding memory provider
    $embeddingMemory = new EmbeddingProvider($platform, $embeddings, $store);
    $memoryProcessor = new MemoryInputProcessor($embeddingMemory);

    $agent = new Agent($platform, $model, [$memoryProcessor]);

Dynamic Memory Control
~~~~~~~~~~~~~~~~~~~~~~

Disable memory for specific calls::

    // Normal call with memory
    $result = $agent->call($messages);

    // Call without memory
    $result = $agent->call($messages, ['use_memory' => false]);

Structured Output
-----------------

Get predictable, type-safe responses from agents.

PHP Class Output
~~~~~~~~~~~~~~~~

::

    use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
    use Symfony\AI\Agent\StructuredOutput\ResponseFormatFactory;

    // Define output structure
    class ProductInfo
    {
        public string $name;
        public string $description;
        public float $price;
        public array $features;
        public bool $inStock;
    }

    // Setup agent with structured output
    $serializer = new Serializer(
        [new ObjectNormalizer()],
        [new JsonEncoder()]
    );
    $processor = new AgentProcessor(
        new ResponseFormatFactory(),
        $serializer
    );

    $agent = new Agent($platform, $model, [$processor], [$processor]);

    // Get structured response
    $messages = new MessageBag(
        Message::ofUser('Tell me about the iPhone 15 Pro')
    );

    $result = $agent->call($messages, [
        'output_structure' => ProductInfo::class
    ]);

    $product = $result->getContent(); // ProductInfo object
    echo $product->name;               // "iPhone 15 Pro"
    echo $product->price;              // 999.99

Array Structure Output
~~~~~~~~~~~~~~~~~~~~~~

::

    $result = $agent->call($messages, [
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'user_profile',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'age' => ['type' => 'integer'],
                        'interests' => [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ]
                    ],
                    'required' => ['name', 'age']
                ]
            ]
        ]
    ]);

    $data = $result->getContent(); // Array with structured data

Input/Output Processors
-----------------------

Processors transform messages and results for specific behaviors.

Input Processors
~~~~~~~~~~~~~~~~

Modify messages before sending to the model::

    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\InputProcessorInterface;

    class TranslationProcessor implements InputProcessorInterface
    {
        public function __construct(
            private string $targetLanguage = 'en'
        ) {}

        public function processInput(Input $input): void
        {
            // Add translation instruction
            $input->messages->append(
                Message::forSystem(
                    "Always respond in {$this->targetLanguage}"
                )
            );
        }
    }

    // Use with agent
    $processor = new TranslationProcessor('fr');
    $agent = new Agent($platform, $model, [$processor]);

Output Processors
~~~~~~~~~~~~~~~~~

Transform results after model response::

    use Symfony\AI\Agent\Output;
    use Symfony\AI\Agent\OutputProcessorInterface;

    class ProfanityFilterProcessor implements OutputProcessorInterface
    {
        public function processOutput(Output $output): void
        {
            $content = $output->result->getContent();
            $filtered = $this->filterProfanity($content);
            
            if ($content !== $filtered) {
                $output->result = new TextResult($filtered);
            }
        }

        private function filterProfanity(string $text): string
        {
            // Filter implementation
            return str_replace(['bad', 'words'], '***', $text);
        }
    }

Chat Sessions
-------------

Maintain conversation context across multiple interactions::

    use Symfony\AI\Agent\Chat;
    use Symfony\AI\Agent\Chat\MessageStore\InMemoryStore;

    // Create chat with message persistence
    $messageStore = new InMemoryStore();
    $chat = new Chat($agent, $messageStore);

    // First message
    $response1 = $chat->send('My name is Alice');
    echo $response1; // "Hello Alice! Nice to meet you."

    // Follow-up (remembers context)
    $response2 = $chat->send('What is my name?');
    echo $response2; // "Your name is Alice."

    // Get conversation history
    $history = $chat->getMessages();

Session Storage Options
~~~~~~~~~~~~~~~~~~~~~~~

::

    use Symfony\AI\Agent\Chat\MessageStore\SessionStore;
    use Symfony\AI\Agent\Chat\MessageStore\CacheStore;

    // Session storage (web applications)
    $sessionStore = new SessionStore($requestStack);
    $chat = new Chat($agent, $sessionStore);

    // Cache storage (persistent)
    $cacheStore = new CacheStore($cachePool);
    $chat = new Chat($agent, $cacheStore);

Advanced Tool Features
----------------------

Agent as Tool
~~~~~~~~~~~~~

Use one agent as a tool for another::

    use Symfony\AI\Agent\Toolbox\Tool\Agent as AgentTool;

    // Create specialized agent
    $researchAgent = new Agent($platform, $model);

    // Wrap as tool
    $agentTool = new AgentTool($researchAgent);

    // Register with toolbox
    $factory = (new MemoryToolFactory())
        ->addTool($agentTool, 'research', 'Research assistant for complex queries');

    $toolbox = new Toolbox($factory, [$agentTool]);

Tool Result Interception
~~~~~~~~~~~~~~~~~~~~~~~~

React to tool execution results::

    use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;

    $dispatcher->addListener(ToolCallsExecuted::class, function (ToolCallsExecuted $event) {
        foreach ($event->toolCallResults as $result) {
            // Log tool usage
            $logger->info('Tool executed', [
                'tool' => $result->toolCall->name,
                'params' => $result->toolCall->arguments,
                'result' => $result->result
            ]);

            // Override response for specific tools
            if ($result->toolCall->name === 'sensitive_data') {
                $event->result = new TextResult('[Data redacted]');
            }
        }
    });

Tool Authorization
~~~~~~~~~~~~~~~~~~

Restrict tool access based on user permissions::

    use Symfony\AI\Agent\Attribute\IsGrantedTool;

    #[IsGrantedTool('ROLE_ADMIN')]
    #[AsTool('delete_user', 'Delete a user from the system')]
    class DeleteUserTool
    {
        public function __invoke(int $userId): string
        {
            // Only accessible by users with ROLE_ADMIN
            return "User $userId deleted";
        }
    }

Built-in Tools
--------------

Symfony AI includes several ready-to-use tools::

    use Symfony\AI\Agent\Toolbox\Tool\Clock;
    use Symfony\AI\Agent\Toolbox\Tool\Wikipedia;
    use Symfony\AI\Agent\Toolbox\Tool\OpenMeteo;
    use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;
    use Symfony\AI\Agent\Toolbox\Tool\Firecrawl;
    use Symfony\AI\Agent\Toolbox\Tool\Tavily;

    // Time and date
    $clock = new Clock();

    // Wikipedia search
    $wikipedia = new Wikipedia();

    // Weather information
    $weather = new OpenMeteo();

    // Semantic search (for RAG)
    $search = new SimilaritySearch($embeddings, $store);

    // Web scraping
    $firecrawl = new Firecrawl($endpoint, $apiKey);

    // Web search
    $tavily = new Tavily($apiKey);

Testing Agents
--------------

Test agents with mock tools and platforms::

    use Symfony\AI\Platform\InMemoryPlatform;

    class AgentTest extends TestCase
    {
        public function testAgentWithTools(): void
        {
            // Mock platform
            $platform = new InMemoryPlatform(
                fn($model, $input) => new ToolCallResult([
                    new ToolCall('test_tool', ['param' => 'value'])
                ])
            );

            // Mock tool
            $tool = $this->createMock(ToolInterface::class);
            $tool->method('__invoke')->willReturn('Tool result');

            // Test agent behavior
            $agent = new Agent($platform, new Model('test'));
            // ... test assertions
        }
    }

Next Steps
----------

* Explore tool development: :doc:`../features/tool-calling`
* Implement RAG patterns: :doc:`../features/rag`
* Learn about memory: :doc:`../features/memory-management`
* See practical examples: :doc:`../guides/building-chatbot`