Venice AI
=========

Venice AI is an open and permissionless AI platform that provides access to a wide range of models for text generation,
embeddings, text-to-speech, speech recognition, image generation and video generation. The bridge supports all of these
capabilities through a unified interface.

For comprehensive information about Venice AI, see the `Venice AI API reference`_.

Setup
-----

Authentication
~~~~~~~~~~~~~~

Venice AI requires an API key, which you can obtain from the `Venice AI dashboard`_.

Usage
-----

Chat Completion
~~~~~~~~~~~~~~~

Basic chat completion example::

    use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('What is the capital of France?'),
    );

    $result = $platform->invoke('venice-uncensored', $messages);

    echo $result->asText();

Streaming
~~~~~~~~~

Chat completions can be streamed by passing the ``stream`` option::

    use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = PlatformFactory::create($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('Tell me a story.'),
    );

    $result = $platform->invoke('venice-uncensored', $messages, [
        'stream' => true,
    ]);

    foreach ($result->asStream() as $chunk) {
        echo $chunk;
    }

Text Embeddings
~~~~~~~~~~~~~~~

Generate vector embeddings from text::

    use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;

    $platform = PlatformFactory::create($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('text-embedding-bge-m3', 'The quick brown fox jumps over the lazy dog.');

    $vector = $result->asVectors()[0];
    echo 'Dimensions: '.$vector->getDimensions();

Image Generation
~~~~~~~~~~~~~~~~

Generate images from text prompts. The result is returned as binary data (base64 decoded)::

    use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;

    $platform = PlatformFactory::create($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('z-image-turbo', 'A beautiful sunset over a mountain range');

    $result->asFile('/path/to/image.png');

Text-to-Speech
~~~~~~~~~~~~~~

Convert text to audio. The result is returned as binary audio data::

    use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = PlatformFactory::create($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('tts-kokoro', new Text('Hello world from Venice'), [
        'voice' => 'af_sky',
    ]);

    echo $result->asBinary();

Speech Recognition
~~~~~~~~~~~~~~~~~~

Transcribe audio files to text::

    use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;
    use Symfony\AI\Platform\Message\Content\Audio;

    $platform = PlatformFactory::create($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('nvidia/parakeet-tdt-0.6b-v3', Audio::fromFile('/path/to/audio.mp3'));

    echo $result->asText();

Video Generation
~~~~~~~~~~~~~~~~

Generate videos from images. The video generation API is queue-based: a request is submitted and a queue ID is returned.
The video can then be retrieved using the queue ID::

    use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;

    $platform = PlatformFactory::create($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('wan-ai/wan2.1-t2v-14b', [
        'prompt' => 'A timelapse of a sunset over a mountain range',
        'image_url' => 'https://example.com/mountain.jpg',
    ]);

    echo 'Queue ID: '.$result->asText();

Model Catalog
~~~~~~~~~~~~~

Unlike most other bridges, Venice uses a dynamic model catalog. The available models and their capabilities are fetched
at runtime from the Venice API (``GET /models``). This means the bridge automatically supports new models as they become
available on the platform, without requiring code changes.

Examples
--------

See the ``examples/venice/`` directory for complete working examples:

* ``chat.php`` - Basic chat completion
* ``chat-as-stream.php`` - Streaming chat completion
* ``embeddings.php`` - Text embeddings
* ``image-generation.php`` - Image generation from text prompt
* ``text-to-speech.php`` - Text-to-speech conversion
* ``transcription.php`` - Audio transcription
* ``video-generation.php`` - Video generation from image

.. _Venice AI API reference: https://docs.venice.ai/api-reference/api-spec
.. _Venice AI dashboard: https://venice.ai/settings/api
