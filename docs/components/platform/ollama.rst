Ollama
======

Ollama is a tool for running large language models locally. It supports chat completion,
text generation, embeddings, tool calling, structured output, and streaming.

For comprehensive information about Ollama, see the `Ollama documentation`_.

Setup
-----

Installation
~~~~~~~~~~~~

First, install and start Ollama by following the `Ollama quickstart guide`_.

Pull the models you want to use:

.. code-block:: terminal

    $ ollama pull llama3.2
    $ ollama pull nomic-embed-text

Authentication
~~~~~~~~~~~~~~

Ollama runs locally and does not require an API key by default. If your Ollama
server is configured with authentication, you can pass an API key::

    use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;

    // Without authentication (default local setup)
    $platform = PlatformFactory::create('http://localhost:11434');

    // With API key authentication
    $platform = PlatformFactory::create('http://localhost:11434', 'your-api-key');

Usage
-----

Chat Completion
~~~~~~~~~~~~~~~

Use a ``MessageBag`` with the chat API::

    use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create('http://localhost:11434');

    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('What is the Symfony framework?'),
    );

    $result = $platform->invoke('llama3.2', $messages);

    echo $result->asText();

Text Generation
~~~~~~~~~~~~~~~

For simple text generation without a message history, pass a string directly::

    use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;

    $platform = PlatformFactory::create('http://localhost:11434');

    $result = $platform->invoke('llama3.2', 'Explain what PHP is in one sentence.');

    echo $result->asText();

Streaming
~~~~~~~~~

Both chat and text generation support streaming. Enable it with the ``stream`` option::

    use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

    $platform = PlatformFactory::create('http://localhost:11434');

    // Chat streaming
    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('Tell me a story.'),
    );

    $result = $platform->invoke('llama3.2', $messages, ['stream' => true]);

    foreach ($result->asTextStream() as $delta) {
        if ($delta instanceof TextDelta) {
            echo $delta;
        }
    }

    // Text generation streaming
    $result = $platform->invoke('llama3.2', 'Tell me a story.', ['stream' => true]);

    foreach ($result->asTextStream() as $delta) {
        if ($delta instanceof TextDelta) {
            echo $delta;
        }
    }

Embeddings
~~~~~~~~~~

Use an embedding model to create vector representations of text::

    use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;

    $platform = PlatformFactory::create('http://localhost:11434');

    $result = $platform->invoke('nomic-embed-text', 'The quick brown fox jumps over the lazy dog.');

    $vectors = $result->asVectors();
    echo 'Dimensions: '.$vectors[0]->getDimensions();

Model Catalog
~~~~~~~~~~~~~

Ollama provides automatic model discovery. The platform queries the Ollama API
to retrieve model capabilities, including support for tool calling, thinking,
vision, and embeddings. This also works with custom models built with a ``Modelfile``::

    use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create('http://localhost:11434');

    // Use any model available on your Ollama server
    $result = $platform->invoke('your-custom-model', new MessageBag(
        Message::ofUser('Hello!'),
    ));

    echo $result->asText();

Model size variants can be used with colon notation::

    $result = $platform->invoke('llama3.2:7b', $messages);

Token Usage Tracking
~~~~~~~~~~~~~~~~~~~~

Token usage is automatically tracked and available in the result metadata::

    use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\TokenUsage\TokenUsage;

    $platform = PlatformFactory::create('http://localhost:11434');

    $messages = new MessageBag(
        Message::ofUser('Hello!'),
    );

    $result = $platform->invoke('llama3.2', $messages, ['stream' => true]);

    // Consume the stream
    foreach ($result->asTextStream() as $delta) {
        if ($delta instanceof TextDelta) {
            echo $delta;
        }
    }

    $tokenUsage = $result->getMetadata()->get('token_usage');

    assert($tokenUsage instanceof TokenUsage);

    echo 'Prompt Tokens: '.$tokenUsage->getPromptTokens().PHP_EOL;
    echo 'Completion Tokens: '.$tokenUsage->getCompletionTokens().PHP_EOL;

Examples
--------

See the ``examples/ollama/`` directory for complete working examples.

.. _Ollama documentation: https://ollama.com/
.. _Ollama quickstart guide: https://github.com/ollama/ollama/blob/main/README.md#quickstart
