Quick Start Guide
=================

This guide will get you up and running with Symfony AI in minutes. We'll build a simple chat application, 
add tool calling, and implement basic RAG functionality.

Your First AI Chat
------------------

Let's start with a basic chat completion:

.. code-block:: php

    <?php
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Agent\Agent;

    // Initialize platform and model
    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
    $model = new Gpt(Gpt::GPT_4O_MINI);
    
    // Create an agent
    $agent = new Agent($platform, $model);
    
    // Prepare messages
    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('What is the capital of France?')
    );
    
    // Get response
    $result = $agent->call($messages);
    echo $result->getContent(); // "The capital of France is Paris."

Using Symfony Service Container
--------------------------------

In a Symfony application, inject the agent service:

Controller Example
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    namespace App\Controller;

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\Routing\Annotation\Route;

    class ChatController extends AbstractController
    {
        #[Route('/chat', methods: ['POST'])]
        public function chat(Request $request, AgentInterface $agent): JsonResponse
        {
            $userMessage = $request->request->get('message');
            
            $messages = new MessageBag(
                Message::forSystem('You are a helpful assistant.'),
                Message::ofUser($userMessage)
            );
            
            $result = $agent->call($messages);
            
            return $this->json([
                'response' => $result->getContent()
            ]);
        }
    }

Configuration
~~~~~~~~~~~~~

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
                system_prompt: 'You are a helpful assistant.'

Adding Tool Calling
-------------------

Enable your AI to execute functions and interact with your application:

Creating a Custom Tool
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

    #[AsTool('get_weather', 'Get current weather for a location')]
    class WeatherTool
    {
        public function __invoke(string $location): array
        {
            // Simulate weather API call
            return [
                'location' => $location,
                'temperature' => rand(15, 30),
                'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)]
            ];
        }
    }

Using Tools with Agent
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;

    // Create tool and toolbox
    $weatherTool = new WeatherTool();
    $toolbox = Toolbox::create($weatherTool);
    $processor = new AgentProcessor($toolbox);
    
    // Create agent with tool support
    $agent = new Agent(
        $platform, 
        $model,
        inputProcessors: [$processor],
        outputProcessors: [$processor]
    );
    
    // Ask about weather
    $messages = new MessageBag(
        Message::ofUser('What\'s the weather in Paris?')
    );
    
    $result = $agent->call($messages);
    echo $result->getContent(); 
    // "The current weather in Paris is 22°C and sunny."

Streaming Responses
-------------------

Stream AI responses for better user experience:

.. code-block:: php

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $messages = new MessageBag(
        Message::ofUser('Tell me a story about a robot.')
    );

    // Enable streaming
    $result = $agent->call($messages, ['stream' => true]);

    // Stream the response
    foreach ($result->getContent() as $chunk) {
        echo $chunk; // Outputs story word by word
        flush();     // Send to browser immediately
    }

Working with Images
-------------------

Process images with multimodal models:

.. code-block:: php

    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Message;

    $messages = new MessageBag(
        Message::ofUser(
            'What do you see in this image?',
            Image::fromFile('/path/to/image.jpg')
        )
    );

    $result = $agent->call($messages);
    echo $result->getContent(); // Description of the image

Implementing Basic RAG
----------------------

Add context-aware responses with vector search:

Setting Up Vector Store
~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\InMemoryStore;
    use Symfony\AI\Store\Indexer;
    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

    // Create store and indexer
    $store = new InMemoryStore();
    $embeddings = new Embeddings(Embeddings::TEXT_3_SMALL);
    $indexer = new Indexer($platform, $embeddings, $store);

    // Index documents
    $documents = [
        new TextDocument('Paris is the capital of France.'),
        new TextDocument('Berlin is the capital of Germany.'),
        new TextDocument('Rome is the capital of Italy.')
    ];

    foreach ($documents as $document) {
        $indexer->index($document);
    }

Using RAG with Agent
~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;

    // Create similarity search tool
    $similaritySearch = new SimilaritySearch($embeddings, $store);
    $toolbox = Toolbox::create($similaritySearch);
    $processor = new AgentProcessor($toolbox);

    // Create RAG-enabled agent
    $agent = new Agent(
        $platform,
        $model,
        [$processor],
        [$processor]
    );

    // Ask questions
    $messages = new MessageBag(
        Message::forSystem('Answer questions using only the similarity_search tool.'),
        Message::ofUser('What is the capital of Germany?')
    );

    $result = $agent->call($messages);
    echo $result->getContent(); // "The capital of Germany is Berlin."

Structured Output
-----------------

Get predictable, typed responses:

Define Output Structure
~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    class WeatherInfo
    {
        public string $location;
        public float $temperature;
        public string $condition;
        public array $forecast;
    }

Get Structured Response
~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
    use Symfony\AI\Agent\StructuredOutput\ResponseFormatFactory;
    use Symfony\Component\Serializer\Encoder\JsonEncoder;
    use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
    use Symfony\Component\Serializer\Serializer;

    // Setup serializer and processor
    $serializer = new Serializer(
        [new ObjectNormalizer()],
        [new JsonEncoder()]
    );
    $processor = new AgentProcessor(
        new ResponseFormatFactory(),
        $serializer
    );

    // Create agent with structured output
    $agent = new Agent($platform, $model, [$processor], [$processor]);

    // Get structured response
    $messages = new MessageBag(
        Message::ofUser('Give me weather info for Paris')
    );

    $result = $agent->call($messages, [
        'output_structure' => WeatherInfo::class
    ]);

    $weather = $result->getContent(); // WeatherInfo object
    echo $weather->location;           // "Paris"
    echo $weather->temperature;        // 22.5

Persistent Chat Sessions
------------------------

Maintain conversation context across requests:

.. code-block:: php

    use Symfony\AI\Agent\Chat;
    use Symfony\AI\Agent\Chat\MessageStore\SessionStore;
    use Symfony\Component\HttpFoundation\RequestStack;

    class ChatService
    {
        private Chat $chat;

        public function __construct(
            AgentInterface $agent,
            RequestStack $requestStack
        ) {
            // Use session to persist messages
            $store = new SessionStore($requestStack);
            $this->chat = new Chat($agent, $store);
        }

        public function sendMessage(string $message): string
        {
            return $this->chat->send($message);
        }
    }

Error Handling
--------------

Handle API errors gracefully:

.. code-block:: php

    use Symfony\AI\Platform\Exception\ContentFilterException;
    use Symfony\AI\Platform\Exception\RuntimeException;

    try {
        $result = $agent->call($messages);
    } catch (ContentFilterException $e) {
        // Handle content filter violations
        echo "Message blocked by content filter";
    } catch (RuntimeException $e) {
        // Handle API errors
        echo "AI service error: " . $e->getMessage();
    }

Testing Your AI Code
--------------------

Use in-memory implementations for testing:

.. code-block:: php

    use Symfony\AI\Platform\InMemoryPlatform;
    use Symfony\AI\Platform\Model;

    class ChatServiceTest extends TestCase
    {
        public function testChat(): void
        {
            // Create test platform with fixed response
            $platform = new InMemoryPlatform('Test response');
            $model = new Model('test-model');
            $agent = new Agent($platform, $model);

            $messages = new MessageBag(
                Message::ofUser('Hello')
            );

            $result = $agent->call($messages);
            
            $this->assertEquals('Test response', $result->getContent());
        }
    }

Next Steps
----------

You've learned the basics! Now explore:

* :doc:`architecture` - Understand the component structure
* :doc:`../components/platform` - Deep dive into the Platform component
* :doc:`../components/agent` - Advanced agent features
* :doc:`../features/tool-calling` - Build complex tools
* :doc:`../guides/implementing-rag` - Production RAG implementation
* :doc:`../reference/configuration` - Full configuration options

Example Applications
--------------------

Check out complete examples in the repository:

* ``examples/`` - Standalone PHP examples for all features
* ``demo/`` - Full Symfony application with UI
* Integration examples for each AI provider
* RAG implementations with different vector stores
* Tool calling and agent examples