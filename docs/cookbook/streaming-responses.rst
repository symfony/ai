Streaming Responses
===================

This guide explains how to enable streaming in Symfony AI so that your
application can display AI-generated text token by token instead of waiting
for the complete response.

What is Streaming?
------------------

Large language models generate text sequentially, word by word. Without
streaming, your application must wait until the entire response has been
generated before rendering anything. With streaming, each token is forwarded
to the client as soon as it is produced via `Server-Sent Events`_ (SSE).

The result is a much snappier user experience: the first word appears within
milliseconds and the text builds up progressively, just like in ChatGPT.

Symfony AI abstracts all SSE parsing and exposes the stream as a plain PHP
:class:`Generator`.

Prerequisites
-------------

* Symfony AI Platform component (``symfony/ai-platform``)
* An API key for at least one supported platform
* For web delivery: a mechanism to push chunks to the browser (see
  :ref:`streaming-in-symfony-controllers`)

Streaming at the Platform Level
---------------------------------

Pass ``'stream' => true`` in the options array when invoking the platform::

    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);

    $messages = new MessageBag(
        Message::forSystem('You are a thoughtful philosopher.'),
        Message::ofUser('What is the purpose of an ant?'),
    );

    $result = $platform->invoke('gpt-4o-mini', $messages, ['stream' => true]);

    foreach ($result->asStream() as $chunk) {
        echo $chunk;
        flush();
    }

The same ``'stream' => true`` option works across all platforms that support
streaming (OpenAI, Anthropic, Mistral, Gemini, Cerebras, …).

Streaming at the Agent Level
------------------------------

When using the :class:`Symfony\\AI\\Agent\\Agent`, pass the same option to
``call()``::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create($_ENV['ANTHROPIC_API_KEY']);
    $agent = new Agent($platform, 'claude-sonnet-4-5-20250929');

    $messages = new MessageBag(
        Message::forSystem('You are a concise assistant.'),
        Message::ofUser('Explain recursion in three sentences.'),
    );

    $result = $agent->call($messages, ['stream' => true]);

    foreach ($result->getContent() as $chunk) {
        echo $chunk;
        flush();
    }

.. note::

    Use ``$result->getContent()`` on an agent result and ``$result->asStream()``
    on a platform result. Both yield the same generator of string chunks.

Streaming with Tool Calls
--------------------------

Streaming works transparently alongside tool calling. The agent completes all
tool call rounds first, and the **final** response is then streamed to the
caller::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\Component\HttpClient\HttpClient;

    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);

    $wikipedia = new Wikipedia(HttpClient::create());
    $toolbox = new Toolbox([$wikipedia]);
    $processor = new AgentProcessor($toolbox);

    $agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor]);
    $messages = new MessageBag(
        Message::ofUser('What is the capital of Austria? Look it up on Wikipedia.')
    );

    $result = $agent->call($messages, ['stream' => true]);

    foreach ($result->getContent() as $chunk) {
        echo $chunk;
        flush();
    }

Handling Thinking Chunks in Streams
-------------------------------------

When using models that support extended reasoning (e.g. Claude with
``thinking`` enabled), the stream may include
:class:`Symfony\\AI\\Platform\\Result\\ThinkingContent` objects alongside
regular text strings. Check the chunk type before echoing::

    use Symfony\AI\Platform\Result\ThinkingContent;

    $result = $platform->invoke('claude-sonnet-4-5', $messages, [
        'stream' => true,
        'thinking' => ['type' => 'enabled', 'budget_tokens' => 5000],
    ]);

    foreach ($result->getContent() as $chunk) {
        if ($chunk instanceof ThinkingContent) {
            // Optionally show the model's internal reasoning
            continue;
        }

        echo $chunk;
        flush();
    }

.. _streaming-in-symfony-controllers:

Streaming in Symfony Controllers
----------------------------------

In a Symfony web application, use :class:`Symfony\\Component\\HttpFoundation\\StreamedResponse`
to push chunks to the browser as they arrive::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\Component\DependencyInjection\Attribute\Target;
    use Symfony\Component\HttpFoundation\StreamedResponse;
    use Symfony\Component\Routing\Attribute\Route;

    #[Route('/chat', name: 'app_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        #[Target('ai.agent.default')]
        AgentInterface $agent,
    ): StreamedResponse {
        $userMessage = $request->getPayload()->getString('message');

        return new StreamedResponse(function () use ($agent, $userMessage): void {
            $messages = new MessageBag(
                Message::forSystem('You are a helpful assistant.'),
                Message::ofUser($userMessage),
            );

            $result = $agent->call($messages, ['stream' => true]);

            foreach ($result->getContent() as $chunk) {
                echo $chunk;
                ob_flush();
                flush();
            }
        });
    }

.. note::

    For a production-ready setup, consider using `Mercure`_ to push SSE
    events from a background worker. This frees the web server from holding
    long-lived HTTP connections.

.. _streaming-with-mercure:

Streaming with Mercure
-----------------------

When using Mercure, publish each chunk as a separate event from a background
Symfony Messenger handler instead of the controller itself:

1. **Controller** – accepts the user message, publishes a job to Messenger,
   and immediately redirects or returns a job ID.
2. **Message handler** – calls the agent with ``'stream' => true``, iterates
   over the generator, and publishes each chunk to a Mercure hub.
3. **Browser** – subscribes to the Mercure topic and appends chunks to the
   DOM in real time.

.. code-block:: yaml

    # config/packages/mercure.yaml
    mercure:
        hubs:
            default:
                url: '%env(MERCURE_URL)%'
                jwt_secret: '%env(MERCURE_JWT_SECRET)%'

::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\Component\DependencyInjection\Attribute\Target;
    use Symfony\Component\Mercure\HubInterface;
    use Symfony\Component\Mercure\Update;
    use Symfony\Component\Messenger\Attribute\AsMessageHandler;

    #[AsMessageHandler]
    final class StreamChatHandler
    {
        public function __construct(
            #[Target('ai.agent.default')]
            private AgentInterface $agent,
            private HubInterface $hub,
        ) {
        }

        public function __invoke(StreamChatMessage $message): void
        {
            $messages = new MessageBag(
                Message::forSystem('You are a helpful assistant.'),
                Message::ofUser($message->userInput),
            );

            $result = $this->agent->call($messages, ['stream' => true]);

            foreach ($result->getContent() as $chunk) {
                $this->hub->publish(new Update(
                    topics: '/chat/'.$message->conversationId,
                    data: json_encode(['chunk' => $chunk]),
                ));
            }

            // Signal end of stream
            $this->hub->publish(new Update(
                topics: '/chat/'.$message->conversationId,
                data: json_encode(['done' => true]),
            ));
        }
    }

Bundle Configuration
---------------------

When using :doc:`../bundles/ai-bundle`, streaming requires no additional
configuration. Set ``'stream' => true`` at call time:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        agent:
            default:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'You are a helpful assistant.'

Inject the agent and call it with streaming enabled in your service or
controller::

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\Component\DependencyInjection\Attribute\Target;

    final class ChatService
    {
        public function __construct(
            #[Target('ai.agent.default')]
            private AgentInterface $agent,
        ) {
        }

        public function streamResponse(string $userInput): \Generator
        {
            $messages = new MessageBag(
                Message::ofUser($userInput),
            );

            $result = $this->agent->call($messages, ['stream' => true]);

            yield from $result->getContent();
        }
    }

Best Practices
--------------

* **Call ``flush()`` after each chunk.** PHP's output buffering will otherwise
  collect chunks and send them in one batch, defeating the purpose of
  streaming.
* **Set appropriate timeouts.** Long-running streams need relaxed PHP and web
  server timeouts (``max_execution_time``, nginx ``proxy_read_timeout``, etc.).
* **Disable output buffering.** In some environments you may need to call
  ``ob_implicit_flush(true)`` or disable buffering in your web server config.
* **Consider Mercure for production.** Holding a long HTTP connection per
  user does not scale. Mercure offloads fan-out and reconnection logic.
* **Handle disconnects gracefully.** Catch ``\Throwable`` inside the stream
  loop so a client disconnecting mid-stream does not leave orphaned API calls.

Complete Examples
-----------------

* `Streaming with OpenAI <https://github.com/symfony/ai/blob/main/examples/openai/stream.php>`_
* `Streaming with Anthropic <https://github.com/symfony/ai/blob/main/examples/anthropic/stream.php>`_
* `Streaming with Mistral <https://github.com/symfony/ai/blob/main/examples/mistral/stream.php>`_
* `Streaming with tool calls (OpenAI) <https://github.com/symfony/ai/blob/main/examples/openai/toolcall-stream.php>`_
* `Streaming with tool calls (Anthropic) <https://github.com/symfony/ai/blob/main/examples/anthropic/toolcall-stream.php>`_
* `Streaming with Cerebras <https://github.com/symfony/ai/blob/main/examples/cerebras/stream.php>`_

Related Documentation
---------------------

* :doc:`../components/platform` – Platform component and result streaming
* :doc:`../components/agent` – Agent component documentation
* :doc:`../bundles/ai-bundle` – AI Bundle configuration reference
* :doc:`multi-agent-orchestration` – Orchestrate multiple specialized agents

.. _`Server-Sent Events`: https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events
.. _`Mercure`: https://mercure.rocks/
