Symfony AI - Chat Component
===========================

The Chat component provides an API to interact with agents, it allows to store messages and retrieve them later
for future chat and context-retrieving purposes.

Installation
------------

Install the component using Composer:

.. code-block:: terminal

    $ composer require symfony/ai-chat

Basic Usage
-----------

To initiate a chat, you need to instantiate the ``Symfony\AI\Chat\Chat`` along
with a ``Symfony\AI\Agent\AgentInterface`` and a ``Symfony\AI\Chat\MessageStoreInterface``::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Chat\Bridge\Local\InMemoryStore;
    use Symfony\AI\Chat\Chat;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;

    $platform = PlatformFactory::create($apiKey);
    $model = new Gpt(Gpt::GPT_4O_MINI);

    $agent = new Agent($platform, $model);
    $chat = new Chat($agent, new InMemoryStore());

    $chat->submit(Message::ofUser('Hello'));


Implementing a Bridge
---------------------

The main extension points of the Chat component is the ``Symfony\AI\Chat\MessageStoreInterface``, that defines the methods
for adding messages to the message store, and returning the messages from a store.

This leads to a store implementing two methods::

    use Symfony\AI\Store\MessageStoreInterface;

    class MyCustomStore implements MessageStoreInterface
    {
        public function save(MessageBag $messages): void
        {
            // Implementation to add a message bag to the store
        }

        public function load(): MessageBag
        {
            // Implementation to return a message bag from the store
        }
    }

Managing a store
----------------

Some store might requires to create table, indexes and so on before storing messages,
the ``Symfony\AI\Chat\ManagedStoreInterface`` defines the methods
to setup and drop the store.

This leads to a store implementing two methods::

    use Symfony\AI\Store\ManagedStoreInterface;
    use Symfony\AI\Store\MessageStoreInterface;

    class MyCustomStore implements ManagedStoreInterface, MessageStoreInterface
    {
        # ...

        public function setup(array $options = []): void
        {
            // Implementation to create the store
        }

        public function drop(): void
        {
            // Implementation to drop the store (and related messages)
        }
    }
