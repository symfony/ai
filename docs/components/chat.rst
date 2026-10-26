Symfony AI - Chat Component
===========================

The Chat component provides an API to interact with agents, it allows to store messages and retrieve them later
for future chat and context-retrieving purposes.

.. warning::

    The Chat component is deprecated since version 0.16. Pass a message store to the agent instead, see
    :ref:`Storing Conversations <agent-storing-conversations>` in the Agent component.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-chat

Basic Usage
-----------

To initiate a chat, you need to instantiate the ``Symfony\AI\Chat\Chat`` along
with a ``Symfony\AI\Agent\AgentInterface`` and a ``Symfony\AI\Chat\MessageStoreInterface``::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Chat\Chat;
    use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
    use Symfony\AI\Platform\Bridge\OpenAi\Factory;
    use Symfony\AI\Platform\Message\Message;

    $platform = Factory::createPlatform($apiKey);

    $agent = new Agent($platform, 'gpt-4o-mini');
    $chat = new Chat($agent, new InMemoryStore());

    $chat->submit(Message::ofUser('Hello'));

Streaming
---------

The Chat component supports streaming responses from the LLM in real-time
using the :method:`Symfony\\AI\\Chat\\ChatInterface::stream` method. This returns
a :class:`Generator` that yields :class:`Symfony\\AI\\Platform\\Result\\Stream\\Delta\\DeltaInterface`
deltas as they are produced by the model::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Chat\Chat;
    use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
    use Symfony\AI\Platform\Bridge\OpenAi\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

    $platform = Factory::createPlatform($apiKey);

    $agent = new Agent($platform, 'gpt-4o-mini');
    $chat = new Chat($agent, new InMemoryStore());

    $chat->initiate(new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
    ));

    foreach ($chat->stream(Message::ofUser('Tell me a story about the sun')) as $delta) {
        if ($delta instanceof TextDelta) {
            echo $delta;
        }
    }

Once the stream is fully consumed, the assistant message is automatically
persisted to the message store along with the user message. This means the
conversation history is kept up-to-date without any additional code.

The persisted message is the assistant turn as the provider produced it, thinking blocks
and signatures included, so the stored conversation can be replayed on a later turn.

Message Stores
--------------

A chat keeps its conversation in a message store of the Agent component. Wrap the store in a
:class:`Symfony\\AI\\Chat\\AgentMessageStore`::

    use Symfony\AI\Agent\Bridge\Redis\MessageStore;
    use Symfony\AI\Chat\AgentMessageStore;
    use Symfony\Component\Uid\UuidV7;

    $store = new MessageStore(new \Redis(['host' => 'localhost']), 'conversations');

    // e.g. the ID of the conversation in your application
    $conversationId = new UuidV7('0199f1a2-6b7c-7d8e-9f01-23456789abcd');

    $chat = new Chat($agent, new AgentMessageStore($store, $conversationId));

See :ref:`Message Store Bridges <agent-message-store-bridges>` for the available stores.
