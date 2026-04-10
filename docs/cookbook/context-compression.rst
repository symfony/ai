Context Compression for Long Conversations
==========================================

When building conversational agents, the message history can grow large over time, increasing
token costs and potentially exceeding model context limits. This guide shows how to implement
an input processor that automatically compresses conversation history.

Sliding Window
--------------

The simplest approach: discard older messages and keep only the most recent ones::

    namespace App\Agent\InputProcessor;

    use Symfony\AI\Agent\Attribute\AsInputProcessor;
    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\InputProcessorInterface;
    use Symfony\AI\Platform\Message\MessageBag;

    #[AsInputProcessor(agent: 'my_agent')]
    final class SlidingWindowInputProcessor implements InputProcessorInterface
    {
        public function __construct(
            private int $maxMessages = 10,
            private int $threshold = 20,
        ) {
        }

        public function processInput(Input $input): void
        {
            $messages = $input->getMessageBag();
            $nonSystemMessages = $messages->withoutSystemMessage()->getMessages();

            if (\count($nonSystemMessages) <= $this->threshold) {
                return;
            }

            $systemMessage = $messages->getSystemMessage();
            $recentMessages = \array_slice($nonSystemMessages, -$this->maxMessages);

            $input->setMessageBag(null !== $systemMessage
                ? new MessageBag($systemMessage, ...$recentMessages)
                : new MessageBag(...$recentMessages),
            );
        }
    }

Summarization
-------------

When older context matters, use an LLM to summarize it and inject the summary into the
system message::

    namespace App\Agent\InputProcessor;

    use Symfony\AI\Agent\Attribute\AsInputProcessor;
    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\InputProcessorInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\PlatformInterface;

    #[AsInputProcessor(agent: 'my_agent')]
    final class SummarizationInputProcessor implements InputProcessorInterface
    {
        public function __construct(
            private PlatformInterface $platform,
            private string $model = 'gpt-4o-mini',
            private int $threshold = 20,
            private int $keepRecent = 6,
        ) {
        }

        public function processInput(Input $input): void
        {
            $messages = $input->getMessageBag();
            $nonSystemMessages = $messages->withoutSystemMessage()->getMessages();

            if (\count($nonSystemMessages) <= $this->threshold) {
                return;
            }

            $toSummarize = \array_slice($nonSystemMessages, 0, -$this->keepRecent);
            $toKeep = \array_slice($nonSystemMessages, -$this->keepRecent);

            $summary = $this->platform->invoke(
                $this->model,
                new MessageBag(Message::ofUser(
                    'Summarize this conversation concisely, focusing on key decisions '
                    .'and current task state: '.\PHP_EOL.$this->formatMessages($toSummarize),
                )),
            )->asText();

            $systemContent = '';
            $systemMessage = $messages->getSystemMessage();
            if (null !== $systemMessage) {
                $systemContent = $systemMessage->getContent().\PHP_EOL.\PHP_EOL;
            }
            $systemContent .= '# Previous Conversation Summary'.\PHP_EOL.\PHP_EOL.$summary;

            $input->setMessageBag(new MessageBag(
                Message::forSystem($systemContent),
                ...$toKeep,
            ));
        }

        private function formatMessages(array $messages): string
        {
            // Format each message as "Role: content" for the summarization prompt
        }
    }

The ``formatMessages()`` method should iterate over the messages and format them as text,
e.g. using ``UserMessage::asText()`` and ``AssistantMessage::getContent()``.

Container Configuration
-----------------------

The ``#[AsInputProcessor(agent: 'my_agent')]`` attribute automatically registers the processor
for the specified agent. Omit the ``agent`` parameter to register it for all agents.

For the summarization processor, wire the platform dependency:

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Agent\InputProcessor\SummarizationInputProcessor:
            $platform: '@ai.platform.openai'
            $model: 'gpt-4o-mini'

Best Practices
--------------

* **Sliding window** is fast and cheap but loses context. **Summarization** preserves context
  but adds latency and cost from the extra LLM call.
* Use a **smaller model** (e.g. ``gpt-4o-mini``, ``gemini-2.0-flash``) for summarization.
* Start with a threshold of **20-30 messages** and adjust based on your use case.
* Always **preserve the system message** during compression.
* Keep **4-8 recent messages** uncompressed so the model has enough immediate context.

Related Documentation
---------------------

* :doc:`chatbot-with-memory` - Building chatbots with memory
* :doc:`../components/agent` - Agent component documentation
* :doc:`../bundles/ai-bundle` - AI Bundle configuration reference
