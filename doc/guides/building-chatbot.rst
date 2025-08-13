Building a Chatbot
==================

This guide walks you through building a fully-featured chatbot using Symfony AI, from a simple 
question-answering bot to an advanced assistant with tools, memory, and streaming capabilities.

Project Setup
-------------

Create a New Symfony Project
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: terminal

    $ symfony new my-chatbot --webapp
    $ cd my-chatbot
    $ composer require symfony/ai-bundle

Configure AI Services
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        agent:
            chatbot:
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Gpt::GPT_4O_MINI
                system_prompt: |
                    You are a helpful and friendly assistant. Be concise but thorough
                    in your responses. Use a conversational tone.

Set Environment Variables
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    # .env.local
    OPENAI_API_KEY=your-api-key-here

Simple Chatbot
--------------

Basic Controller
~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    namespace App\Controller;

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Annotation\Route;

    class ChatController extends AbstractController
    {
        #[Route('/', name: 'chat_index')]
        public function index(): Response
        {
            return $this->render('chat/index.html.twig');
        }

        #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
        public function chat(Request $request, AgentInterface $agent): JsonResponse
        {
            $data = json_decode($request->getContent(), true);
            $userMessage = $data['message'] ?? '';

            if (empty($userMessage)) {
                return $this->json(['error' => 'Message is required'], 400);
            }

            $messages = new MessageBag(
                Message::ofUser($userMessage)
            );

            try {
                $result = $agent->call($messages);
                return $this->json([
                    'response' => $result->getContent()
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Failed to generate response'
                ], 500);
            }
        }
    }

Frontend Template
~~~~~~~~~~~~~~~~~

.. code-block:: html+twig

    {# templates/chat/index.html.twig #}
    {% extends 'base.html.twig' %}

    {% block title %}AI Chatbot{% endblock %}

    {% block body %}
    <div class="container mt-5">
        <h1>AI Chatbot</h1>
        
        <div id="chat-container" class="border rounded p-3 mb-3" style="height: 400px; overflow-y: scroll;">
            <div id="messages"></div>
        </div>
        
        <div class="input-group">
            <input type="text" id="message-input" class="form-control" placeholder="Type your message...">
            <button id="send-button" class="btn btn-primary">Send</button>
        </div>
    </div>
    {% endblock %}

    {% block javascripts %}
    <script>
        const messagesDiv = document.getElementById('messages');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-button');

        function addMessage(content, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message mb-2 ${isUser ? 'text-end' : ''}`;
            messageDiv.innerHTML = `
                <span class="badge ${isUser ? 'bg-primary' : 'bg-secondary'}">
                    ${isUser ? 'You' : 'AI'}
                </span>
                <p class="mb-0 ${isUser ? 'text-end' : ''}">${content}</p>
            `;
            messagesDiv.appendChild(messageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;

            addMessage(message, true);
            messageInput.value = '';
            sendButton.disabled = true;

            try {
                const response = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({message})
                });

                const data = await response.json();
                if (data.error) {
                    addMessage('Error: ' + data.error);
                } else {
                    addMessage(data.response);
                }
            } catch (error) {
                addMessage('Failed to send message');
            } finally {
                sendButton.disabled = false;
            }
        }

        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    </script>
    {% endblock %}

Stateful Conversations
----------------------

Chat Service with Memory
~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    namespace App\Service;

    use Symfony\AI\Agent\AgentInterface;
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
            // Use session to persist conversation
            $messageStore = new SessionStore($requestStack);
            $this->chat = new Chat($agent, $messageStore);
        }

        public function sendMessage(string $message): string
        {
            return $this->chat->send($message);
        }

        public function getHistory(): array
        {
            return $this->chat->getMessages()->toArray();
        }

        public function clearHistory(): void
        {
            $this->chat->clear();
        }
    }

Updated Controller
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request, ChatService $chatService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if ($userMessage === '/clear') {
            $chatService->clearHistory();
            return $this->json(['response' => 'Conversation cleared']);
        }

        try {
            $response = $chatService->sendMessage($userMessage);
            return $this->json(['response' => $response]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/chat/history', name: 'api_chat_history', methods: ['GET'])]
    public function history(ChatService $chatService): JsonResponse
    {
        return $this->json(['history' => $chatService->getHistory()]);
    }

Adding Tools
------------

Weather Tool
~~~~~~~~~~~~

.. code-block:: php

    <?php
    namespace App\Tool;

    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
    use Symfony\Contracts\HttpClient\HttpClientInterface;

    #[AsTool('get_weather', 'Get current weather for a location')]
    class WeatherTool
    {
        public function __construct(
            private HttpClientInterface $httpClient
        ) {}

        /**
         * @param string $location City name or coordinates
         */
        public function __invoke(string $location): array
        {
            // Use OpenMeteo API (free, no key required)
            $response = $this->httpClient->request('GET', 'https://geocoding-api.open-meteo.com/v1/search', [
                'query' => ['name' => $location, 'count' => 1]
            ]);

            $geocoding = $response->toArray();
            if (empty($geocoding['results'])) {
                return ['error' => 'Location not found'];
            }

            $lat = $geocoding['results'][0]['latitude'];
            $lon = $geocoding['results'][0]['longitude'];

            $weather = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'current_weather' => true
                ]
            ])->toArray();

            return [
                'location' => $location,
                'temperature' => $weather['current_weather']['temperature'],
                'windspeed' => $weather['current_weather']['windspeed'],
                'description' => $this->getWeatherDescription($weather['current_weather']['weathercode'])
            ];
        }

        private function getWeatherDescription(int $code): string
        {
            return match(true) {
                $code === 0 => 'Clear sky',
                $code <= 3 => 'Partly cloudy',
                $code <= 48 => 'Foggy',
                $code <= 65 => 'Rainy',
                $code <= 86 => 'Snowy',
                $code <= 99 => 'Thunderstorm',
                default => 'Unknown'
            };
        }
    }

Configure Agent with Tools
~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        agent:
            chatbot:
                tools:
                    - '@App\Tool\WeatherTool'
                    - 'Symfony\AI\Agent\Toolbox\Tool\Clock'

Streaming Responses
-------------------

Streaming Controller
~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\Component\HttpFoundation\StreamedResponse;

    #[Route('/api/chat/stream', name: 'api_chat_stream', methods: ['POST'])]
    public function streamChat(Request $request, AgentInterface $agent): StreamedResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        $messages = new MessageBag(
            Message::ofUser($userMessage)
        );

        return new StreamedResponse(function() use ($agent, $messages) {
            // Set up SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            try {
                $result = $agent->call($messages, ['stream' => true]);
                
                foreach ($result->getContent() as $chunk) {
                    echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                }
                
                echo "data: [DONE]\n\n";
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            }
            
            ob_flush();
            flush();
        });
    }

Frontend for Streaming
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: javascript

    async function sendStreamingMessage() {
        const message = messageInput.value.trim();
        if (!message) return;

        addMessage(message, true);
        messageInput.value = '';
        
        const aiMessageDiv = document.createElement('div');
        aiMessageDiv.className = 'message mb-2';
        aiMessageDiv.innerHTML = `
            <span class="badge bg-secondary">AI</span>
            <p class="mb-0" id="streaming-response"></p>
        `;
        messagesDiv.appendChild(aiMessageDiv);
        
        const responseP = document.getElementById('streaming-response');
        let fullResponse = '';

        const eventSource = new EventSource('/api/chat/stream?' + 
            new URLSearchParams({message}));

        eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            
            if (data === '[DONE]') {
                eventSource.close();
                return;
            }
            
            if (data.chunk) {
                fullResponse += data.chunk;
                responseP.textContent = fullResponse;
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
            
            if (data.error) {
                responseP.textContent = 'Error: ' + data.error;
                eventSource.close();
            }
        };

        eventSource.onerror = () => {
            eventSource.close();
        };
    }

Adding Personality
------------------

Custom System Prompts
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    namespace App\Service;

    class PersonalityService
    {
        public function getPersonality(string $type): string
        {
            return match($type) {
                'professional' => 'You are a professional assistant. Be formal, precise, and thorough.',
                'friendly' => 'You are a friendly companion. Be warm, casual, and engaging.',
                'teacher' => 'You are a patient teacher. Explain concepts clearly with examples.',
                'creative' => 'You are a creative muse. Be imaginative, inspiring, and unconventional.',
                default => 'You are a helpful assistant.'
            };
        }
    }

    // In controller
    #[Route('/api/chat/personality', name: 'api_set_personality', methods: ['POST'])]
    public function setPersonality(
        Request $request,
        PersonalityService $personalityService,
        AgentInterface $agent
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $personality = $data['personality'] ?? 'default';
        
        $systemPrompt = $personalityService->getPersonality($personality);
        
        // Store in session for future requests
        $session = $request->getSession();
        $session->set('chat_personality', $systemPrompt);
        
        return $this->json(['success' => true]);
    }

File Upload Support
-------------------

Handle Images
~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Message\Content\Image;

    #[Route('/api/chat/upload', name: 'api_chat_upload', methods: ['POST'])]
    public function uploadChat(
        Request $request,
        AgentInterface $agent
    ): JsonResponse {
        $message = $request->request->get('message', 'What is in this image?');
        $file = $request->files->get('image');
        
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }
        
        $messages = new MessageBag(
            Message::ofUser(
                $message,
                Image::fromFile($file->getPathname())
            )
        );
        
        try {
            $result = $agent->call($messages);
            return $this->json(['response' => $result->getContent()]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

Rate Limiting
-------------

Implement Rate Limiting
~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\Component\RateLimiter\RateLimiterFactory;

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        AgentInterface $agent,
        RateLimiterFactory $chatLimiter
    ): JsonResponse {
        // Create limiter for this user/IP
        $limiter = $chatLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);
        
        if (!$limit->isAccepted()) {
            return $this->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ], 429);
        }
        
        // ... rest of chat logic
    }

Configure Rate Limiter
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/rate_limiter.yaml
    framework:
        rate_limiter:
            chat:
                policy: 'sliding_window'
                limit: 10
                interval: '1 minute'

Error Handling
--------------

Comprehensive Error Handler
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    namespace App\Service;

    use Symfony\AI\Platform\Exception\ContentFilterException;
    use Symfony\AI\Platform\Exception\RuntimeException;
    use Psr\Log\LoggerInterface;

    class ChatErrorHandler
    {
        public function __construct(
            private LoggerInterface $logger
        ) {}

        public function handleError(\Exception $e): array
        {
            if ($e instanceof ContentFilterException) {
                $this->logger->warning('Content filtered', [
                    'message' => $e->getMessage()
                ]);
                return [
                    'error' => 'Your message was filtered for safety reasons',
                    'type' => 'content_filter'
                ];
            }

            if ($e instanceof RuntimeException) {
                if (str_contains($e->getMessage(), 'rate_limit')) {
                    return [
                        'error' => 'API rate limit reached. Please try again later.',
                        'type' => 'rate_limit'
                    ];
                }
                
                if (str_contains($e->getMessage(), 'timeout')) {
                    return [
                        'error' => 'Request timed out. Please try again.',
                        'type' => 'timeout'
                    ];
                }
            }

            $this->logger->error('Chat error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'error' => 'An unexpected error occurred',
                'type' => 'unknown'
            ];
        }
    }

Testing
-------

Test the Chatbot
~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    namespace App\Tests\Controller;

    use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
    use Symfony\AI\Platform\InMemoryPlatform;

    class ChatControllerTest extends WebTestCase
    {
        public function testChatEndpoint(): void
        {
            $client = static::createClient();
            
            // Mock the AI platform
            $platform = new InMemoryPlatform('Test response');
            self::getContainer()->set('ai.platform.openai', $platform);
            
            $client->request('POST', '/api/chat', [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode(['message' => 'Hello']));
            
            $this->assertResponseIsSuccessful();
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals('Test response', $response['response']);
        }
    }

Deployment
----------

Production Configuration
~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/prod/ai.yaml
    ai:
        agent:
            chatbot:
                model:
                    options:
                        temperature: 0.5  # More consistent in production
                        max_tokens: 1000  # Limit response length
        
        http_client:
            timeout: 30
            max_retries: 3

Performance Optimization
~~~~~~~~~~~~~~~~~~~~~~~~

1. **Cache frequently asked questions**
2. **Use connection pooling for API calls**
3. **Implement request queuing for high traffic**
4. **Use CDN for static assets**
5. **Enable OPcache for PHP**

Security Checklist
~~~~~~~~~~~~~~~~~~

✓ API keys in environment variables
✓ Rate limiting enabled
✓ Input validation and sanitization
✓ Content filtering active
✓ HTTPS enforced
✓ CORS configured properly
✓ Session security configured
✓ Error messages don't leak sensitive data

Next Steps
----------

* Add more tools: :doc:`../features/tool-calling`
* Implement RAG: :doc:`implementing-rag`
* Add voice support: :doc:`../providers/openai`
* Deploy to production: :doc:`../resources/performance`