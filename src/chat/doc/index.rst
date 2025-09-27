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

