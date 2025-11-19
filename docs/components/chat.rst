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
with a ``Symfony\AI\Agent\AgentInterface`` and a ``Symfony\AI\Chat\MessageStoreInterface``::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Chat\Bridge\Local\InMemoryStore;
    use Symfony\AI\Chat\Chat;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;

    $platform = PlatformFactory::create($apiKey);

    $agent = new Agent($platform, 'gpt-4o-mini');
    $chat = new Chat($agent, new InMemoryStore());

    $chat->submit(Message::ofUser('Hello'));

You can find more advanced usage in combination with an Agent using the store for long-term context:

* `External services storage with Cache`_
* `Current session context storage with HttpFoundation session`_
* `Current process context storage with InMemory`_
* `Long-term context with Meilisearch`_
* `Long-term context with Pogocache`_
* `Long-term context with Redis`_

Supported Message stores
------------------------

* `Cache`_
* `HttpFoundation session`_
* `InMemory`_
* `Meilisearch`_
* `Pogocache`_
* `Redis`_

Implementing a Bridge
---------------------

The main extension points of the Chat component is the ``Symfony\AI\Chat\MessageStoreInterface``, that defines the methods
for adding messages to the message store, and returning the messages from a store.

This leads to a store implementing two methods::

    use Symfony\AI\Platform\Message\MessageBag;
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

.. _`External services storage with Cache`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-cache.php
.. _`Current session context storage with HttpFoundation session`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-session.php
.. _`Current process context storage with InMemory`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat.php
.. _`Long-term context with Meilisearch`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-meilisearch.php
.. _`Long-term context with Pogocache`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-pogocache.php
.. _`Long-term context with Redis`: https://github.com/symfony/ai/blob/main/examples/chat/persistent-chat-redis.php
.. _`Cache`: https://symfony.com/doc/current/components/cache.html
.. _`InMemory`: https://www.php.net/manual/en/language.types.array.php
.. _`HttpFoundation session`: https://developers.cloudflare.com/vectorize/
.. _`Meilisearch`: https://www.meilisearch.com/
.. _`Pogocache`: https://pogocache.com/
.. _`Redis`: https://redis.io/
