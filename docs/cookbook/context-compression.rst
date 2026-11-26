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

Step 5: Trigger on Tokens Instead of Message Count
--------------------------------------------------

Counting messages is a rough guide. Ten short questions cost less than one pasted log file, so the
message count says little about how close the request is to the context limit. A better trigger is
the token usage the platform already reported.

Each assistant message carries the metadata of its own model request. The ``token_usage`` entry holds
a :class:`Symfony\\AI\\Platform\\TokenUsage\\TokenUsageInterface`, and ``getTotalTokens()`` is the size of that
request plus its answer. That is a good estimate for the next request, which starts with the same
messages::

    namespace App\Agent\InputProcessor;

    use Symfony\AI\Platform\Message\AssistantMessage;
    use Symfony\AI\Platform\Message\MessageInterface;

    final class TokenThreshold
    {
        public function __construct(
            private int $maxTokens = 100000,
        ) {
        }

        /**
         * @param list<MessageInterface> $messages
         */
        public function isExceeded(array $messages): bool
        {
            foreach ($messages as $message) {
                if (!$message instanceof AssistantMessage) {
                    continue;
                }

                $usage = $message->getMetadata()->get('token_usage');
                if (($usage?->getTotalTokens() ?? 0) > $this->maxTokens) {
                    return true;
                }
            }

            return false;
        }
    }

Both processors use it in place of the message count. Replace the ``$threshold`` argument and the
early return::

    public function __construct(
        private int $maxMessages = 10,
        private TokenThreshold $threshold = new TokenThreshold(),
    ) {
    }

    public function processInput(Input $input): void
    {
        $messages = $input->getMessageBag();
        $nonSystemMessages = $messages->withoutSystemMessage()->getMessages();

        if (!$this->threshold->isExceeded($nonSystemMessages)) {
            return;
        }

        // ... compress as before
    }

The summarization processor changes the same way. Three details matter:

* The check looks at **every** assistant message, not only the last one. The store keeps the full
  history, so after a compressed request the last message reports a small usage. If only that one
  counted, the next call would send the full history again. Once any request has exceeded the limit,
  the history stays above it and the processor keeps compressing.
* The usage does not include the new user message and tool results that come after the last answer.
  Set ``maxTokens`` well below the model's context window to leave room for them.
* A platform that reports no usage never triggers the compression. A conversation without any
  assistant message yet is not compressed either.

.. note::

    The summarization processor calls the model again on every request above the limit. Cache the
    summary, for example keyed by the ID of the last summarized message, if that cost matters.

What an Input Processor Can and Cannot See
------------------------------------------

An input processor runs once per ``Agent::call()``, before the model is asked for the first time.
When the model calls tools, the agent appends the tool calls and their results to the request and
asks the model again. The processor does not run again for these rounds.

This has two consequences:

* A conversation over many turns is covered. Every new turn starts with a fresh check.
* A single turn that grows over many tool rounds is not covered. The processor only saw the
  messages before the first round, so a tool that returns large results can still exceed the limit
  within one turn.

Compression never changes the stored conversation. The agent saves the full history to the message
store before the input processors run, and the processors only replace the message bag of the
request. The store, and the messages returned by the agent, still contain every message.

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
* **Tune the Threshold**: Start with 20-30 messages, or with about 60-70% of the context window
  when you count tokens, and adjust based on your use case
* **Preserve the System Message**: Compression must never drop the agent's instructions
* **Keep Recent Messages Uncompressed**: 4-8 verbatim messages give the model enough immediate
  context

Learn More
----------

* :doc:`chatbot-with-memory` - Build chatbots that remember user preferences and conversation history
* :doc:`../components/agent` - Agent component documentation
* :doc:`../bundles/ai-bundle` - AI Bundle configuration reference
