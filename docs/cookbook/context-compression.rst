.. card:
    title: Context Compression
    description: Keep long conversations within context limits using a sliding window or summarization.
    icon: arrows-minimize
    components: Agent

Context Compression for Long Conversations
==========================================

When building conversational agents, the message history grows over time, increasing token costs
and potentially exceeding model context limits. In this guide you will build input processors that
automatically compress the conversation history — first with a simple sliding window, then with
LLM-based summarization.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component
* OpenAI API key (or any other supported platform)

Step 1: Install Packages
------------------------

Install the Platform and Agent components via Composer::

    composer require symfony/ai-platform symfony/ai-agent

Step 2: Create a Sliding Window Processor
-----------------------------------------

An input processor implements :class:`Symfony\\AI\\Agent\\InputProcessorInterface` and can modify
the message bag before it is sent to the model. The simplest compression strategy discards older
messages and keeps only the most recent ones::

    namespace App\Agent\InputProcessor;

    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\InputProcessorInterface;
    use Symfony\AI\Platform\Message\MessageBag;

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

The ``threshold`` prevents the processor from running on short conversations, and the system
message is always preserved so the agent keeps its instructions.

Step 3: Configure the Agent
---------------------------

Pass the processor to the agent. Processors are applied in order on every call::

    use Symfony\AI\Agent\Agent;

    $agent = new Agent(
        $platform,
        'gpt-4o-mini',
        [new SlidingWindowInputProcessor()],
    );

Whenever the conversation exceeds 20 messages, only the 10 most recent ones are sent to the
model — older messages are dropped silently.

Step 4: Summarize Instead of Discarding
---------------------------------------

A sliding window loses context. When older messages matter, use an LLM to summarize them and
inject the summary into the system message::

    namespace App\Agent\InputProcessor;

    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\InputProcessorInterface;
    use Symfony\AI\Platform\Message\AssistantMessage;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\Message\UserMessage;
    use Symfony\AI\Platform\PlatformInterface;

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
            $lines = [];
            foreach ($messages as $message) {
                if ($message instanceof UserMessage) {
                    $lines[] = 'User: '.$message->asText();
                }

                if ($message instanceof AssistantMessage) {
                    $lines[] = 'Assistant: '.$message->asText();
                }
            }

            return \implode(\PHP_EOL, $lines);
        }
    }

The older messages are condensed into a summary by a separate LLM call, while the most recent
messages stay untouched so the agent has full detail for the immediate context.

Using the AI Bundle?
--------------------

If you use the AI Bundle, the :class:`Symfony\\AI\\Agent\\Attribute\\AsInputProcessor` attribute
registers the processor for a specific agent — or for all agents when the ``agent`` parameter is
omitted::

    use Symfony\AI\Agent\Attribute\AsInputProcessor;

    #[AsInputProcessor(agent: 'my_agent')]
    final class SummarizationInputProcessor implements InputProcessorInterface
    {
        // ...
    }

Wire the platform dependency for the summarization processor:

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Agent\InputProcessor\SummarizationInputProcessor:
            $platform: '@ai.platform.openai'
            $model: 'gpt-4o-mini'

Best Practices
--------------

* **Pick the Right Strategy**: A sliding window is fast and free but loses context; summarization
  preserves context at the cost of latency and an extra LLM call
* **Use a Smaller Model**: Summarization does not need your strongest model — a small one
  (e.g. ``gpt-4o-mini``, ``gemini-2.0-flash``) keeps the overhead low
* **Tune the Threshold**: Start with 20-30 messages and adjust based on your use case
* **Preserve the System Message**: Compression must never drop the agent's instructions
* **Keep Recent Messages Uncompressed**: 4-8 verbatim messages give the model enough immediate
  context

Learn More
----------

* :doc:`chatbot-with-memory` - Build chatbots that remember user preferences and conversation history
* :doc:`../components/agent` - Agent component documentation
* :doc:`../bundles/ai-bundle` - AI Bundle configuration reference
