Symfony AI - Chat Component
===========================

The Chat component provides an API to interact with agents, it allows to store messages and retrieve them later
for future chat and context-retrieving purposes.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-chat

Basic Usage
-----------

To initiate a chat, you need to instantiate the ``Symfony\AI\Chat\Chat`` along
with a ``Symfony\AI\Agent\AgentInterface`` and a ``Symfony\AI\Chat\MessageStoreInterface`` & ``Symfony\AI\Chat\ManagedStoreInterface``::

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

.. note::

    Due to implementations limitations, using streaming with :class:`Symfony\\AI\\Chat\\Bridge\\Session\\MessageStore` is *not* recommended.

Code Examples
~~~~~~~~~~~~~

* `Streaming Chat`_

You can find more advanced usage in combination with an Agent using the store for long-term context:

* `External services storage with Cache`_
* `Long-term context with Doctrine DBAL`_
* `Current session context storage with HttpFoundation session`_
* `Current process context storage with InMemory`_
* `Long-term context with Cloudflare`_
* `Long-term context with Meilisearch`_
* `Long-term context with MongoDb`_
* `Long-term context with Pogocache`_
* `Long-term context with Redis`_
* `Long-term context with SurrealDb`_

Supported Message stores
------------------------

* `Cache`_
* `Cloudflare`_
* `Doctrine DBAL`_
* `HttpFoundation session`_
* `InMemory`_
* `Meilisearch`_
* `MongoDb`_
* `Pogocache`_
* `Redis`_
* `SurrealDb`_

Implementing a Bridge
---------------------

The main extension points of the Chat component is the :class:`Symfony\\AI\\Chat\\MessageStoreInterface`, that defines the methods
for adding messages to the message store, and returning the messages from a store.

This leads to a store implementing two methods::

    use Symfony\AI\Chat\MessageStoreInterface;
    use Symfony\AI\Platform\Message\MessageBag;

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
the :class:`Symfony\\AI\\Chat\\ManagedStoreInterface` defines the methods
to setup and drop the store.

This leads to a store implementing two methods::

    use Symfony\AI\Chat\ManagedStoreInterface;
    use Symfony\AI\Chat\MessageStoreInterface;

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

Commands
--------

While using the ``Chat`` component in your Symfony application along with the ``AiBundle``,
you can use the ``bin/console ai:message-store:setup`` command to initialize the message
store and ``bin/console ai:message-store:drop`` to clean up the message store:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        # ...

        message_store:
            cache:
                symfonycon:
                    service: 'cache.app'

.. code-block:: terminal

    $ php bin/console ai:message-store:setup symfonycon
    $ php bin/console ai:message-store:drop symfonycon

.. _`Streaming Chat`: https://github.com/symfony/ai/blob/main/examples/chat/stream-chat.php
.. _`External services storage with Cache`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-cache.php
.. _`Long-term context with Doctrine DBAL`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-doctrine-dbal.php
.. _`Current session context storage with HttpFoundation session`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-session.php
.. _`Current process context storage with InMemory`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat.php
.. _`Long-term context with Cloudflare`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-cloudflare.php
.. _`Long-term context with Meilisearch`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-meilisearch.php
.. _`Long-term context with MongoDb`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-mongodb.php
.. _`Long-term context with Pogocache`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-pogocache.php
.. _`Long-term context with Redis`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-redis.php
.. _`Long-term context with SurrealDb`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-surrealdb.php
.. _`Cache`: https://symfony.com/doc/current/components/cache.html
.. _`Cloudflare`: https://developers.cloudflare.com/kv/
.. _`Doctrine DBAL`: https://www.doctrine-project.org/projects/dbal.html
.. _`InMemory`: https://www.php.net/manual/en/language.types.array.php
.. _`HttpFoundation session`: https://symfony.com/doc/current/session.html
.. _`Meilisearch`: https://www.meilisearch.com/
.. _`MongoDb`: https://www.mongodb.com/
.. _`Pogocache`: https://pogocache.com/
.. _`Redis`: https://redis.io/
.. _`SurrealDb`: https://surrealdb.com/
