Bifrost
=======

Bifrost is an open-source, self-hosted LLM gateway exposing a unified,
OpenAI-compatible HTTP API in front of many AI providers (OpenAI, Anthropic, AWS
Bedrock, Google Gemini, Cohere, Mistral, …). Models are addressed with the
``provider/model`` notation, e.g. ``openai/gpt-4o-mini`` or ``anthropic/claude-3-opus``.

The Symfony AI Platform component ships a bridge that exposes Bifrost's chat
completions, embeddings, text-to-speech, speech-to-text and image-generation
endpoints, backed by a dynamically loaded model catalogue.

For comprehensive information about Bifrost, see the `Bifrost documentation`_.

Setup
-----

Bifrost is self-hosted, so the bridge always needs to know where the instance
runs. Provide either an ``endpoint`` (the Bifrost base URL) **or** a
pre-configured HTTP client that already carries the base URI. An API key is
optional and, when provided, is attached as an ``Authorization: Bearer <api-key>``
header.

.. code-block:: bash

    BIFROST_ENDPOINT=http://localhost:8080
    # Optional, depending on how the gateway is secured
    BIFROST_API_KEY=

Create the platform from an endpoint::

    use Symfony\AI\Platform\Bridge\Bifrost\Factory;

    $platform = Factory::createPlatform($_ENV['BIFROST_API_KEY'], $_ENV['BIFROST_ENDPOINT']);

Alternatively, omit the ``endpoint`` and pass a pre-configured ``ScopingHttpClient``
that already carries the base URI (and, optionally, the authorization header)::

    use Symfony\AI\Platform\Bridge\Bifrost\Factory;
    use Symfony\Component\HttpClient\HttpClient;
    use Symfony\Component\HttpClient\ScopingHttpClient;

    $httpClient = ScopingHttpClient::forBaseUri(HttpClient::create(), 'http://localhost:8080/', [
        'auth_bearer' => $_ENV['BIFROST_API_KEY'],
    ]);

    $platform = Factory::createPlatform(httpClient: $httpClient);

Usage
-----

Models are resolved through a dynamic catalogue: the list is fetched lazily from
the ``GET /v1/models`` endpoint of the Bifrost instance on first use. Unknown
model names still work — the bridge falls back to a naming convention to infer
the capability (chat, embeddings, audio or image).

Chat Completions
~~~~~~~~~~~~~~~~

Pass a ``MessageBag`` to a chat model to obtain a ``TextResult``::

    use Symfony\AI\Platform\Bridge\Bifrost\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($_ENV['BIFROST_API_KEY'], $_ENV['BIFROST_ENDPOINT']);

    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('What is the capital of France?'),
    );

    $result = $platform->invoke('openai/gpt-4o-mini', $messages);

    echo $result->asText();

Set ``stream: true`` to consume the answer as it is generated::

    $result = $platform->invoke('openai/gpt-4o-mini', $messages, [
        'stream' => true,
    ]);

    foreach ($result->asTextStream() as $delta) {
        echo $delta;
    }

Embeddings
~~~~~~~~~~

Invoke an embedding model with a string to obtain the vectors::

    $platform = Factory::createPlatform($_ENV['BIFROST_API_KEY'], $_ENV['BIFROST_ENDPOINT']);

    $result = $platform->invoke('openai/text-embedding-3-small', 'The quick brown fox jumps over the lazy dog.');

    $vectors = $result->asVectors();

Text-to-Speech
~~~~~~~~~~~~~~

Text-to-speech is routed through ``POST /v1/audio/speech`` and returns a
``BinaryResult``. The ``voice`` option is required::

    use Symfony\AI\Platform\Bridge\Bifrost\Audio\Voice;
    use Symfony\AI\Platform\Bridge\Bifrost\Factory;

    $platform = Factory::createPlatform($_ENV['BIFROST_API_KEY'], $_ENV['BIFROST_ENDPOINT']);

    $result = $platform->invoke('openai/tts-1', 'Hello, welcome to Symfony AI on Bifrost!', [
        'voice' => Voice::ALLOY,
        'response_format' => 'mp3',
    ]);

    file_put_contents('/tmp/speech.mp3', $result->asBinary());

Common options:

* ``voice`` (required) — one of the ``Bifrost\Audio\Voice`` constants (``alloy``,
  ``ash``, ``ballad``, ``coral``, ``echo``, ``fable``, ``nova``, ``onyx``,
  ``sage``, ``shimmer``, ``verse``).
* ``response_format`` — one of the ``Bifrost\Audio\Format`` constants (``mp3``,
  ``opus``, ``aac``, ``flac``, ``wav``, ``pcm``).
* ``speed`` — playback speed multiplier.

Streaming text-to-speech is not supported.

Speech-to-Text
~~~~~~~~~~~~~~

Pass an ``Audio`` content object to a transcription model to obtain a
``TextResult``::

    use Symfony\AI\Platform\Bridge\Bifrost\Factory;
    use Symfony\AI\Platform\Message\Content\Audio;

    $platform = Factory::createPlatform($_ENV['BIFROST_API_KEY'], $_ENV['BIFROST_ENDPOINT']);

    $result = $platform->invoke('openai/whisper-1', Audio::fromFile('/path/to/audio.mp3'));

    echo $result->asText();

Common options:

* ``task`` — ``Bifrost\Audio\Task::TRANSCRIPTION`` (default, keeps the source
  language) or ``Bifrost\Audio\Task::TRANSLATION`` (translates to English,
  routed through ``/v1/audio/translations``).
* ``language`` — BCP-47 hint for the source language.
* ``verbose`` — set to ``true`` to request the verbose payload with segments and
  timestamps.

Image Generation
~~~~~~~~~~~~~~~~

Invoke an image model with a prompt and read the generated images from the
``ImageResult``::

    use Symfony\AI\Platform\Bridge\Bifrost\Factory;
    use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageResult;

    $platform = Factory::createPlatform($_ENV['BIFROST_API_KEY'], $_ENV['BIFROST_ENDPOINT']);

    $result = $platform->invoke('openai/dall-e-3', 'A friendly red panda holding a Symfony logo.', [
        'size' => '1024x1024',
        'response_format' => 'url',
    ])->getResult();

    \assert($result instanceof ImageResult);

    foreach ($result->getContent() as $image) {
        echo $image->url.\PHP_EOL;
    }

With ``response_format: 'b64_json'`` the generated images are returned as
base64-encoded data instead of URLs.

Bundle Configuration
--------------------

When the bridge is used through ``symfony/ai-bundle``, configure it under the
``bifrost`` platform node:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            bifrost:
                endpoint: '%env(BIFROST_ENDPOINT)%'
                api_key: '%env(BIFROST_API_KEY)%'

When ``endpoint`` is left empty, the ``http_client`` option must point at a
client service that is already scoped to the Bifrost base URI (for instance one
declared under ``framework.http_client.scoped_clients``):

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            bifrost:
                http_client: 'bifrost.client'

The resulting ``ai.platform.bifrost`` service is autowireable as
``PlatformInterface $bifrost``.

Examples
--------

See the ``examples/bifrost/`` directory for complete working examples:

* ``chat.php`` — Chat completion with a system and a user message
* ``stream.php`` — Streaming chat completion
* ``embeddings.php`` — Text embeddings
* ``text-to-speech.php`` — Speech synthesis to audio bytes
* ``speech-to-text.php`` — Transcription from a local audio file
* ``image-generation.php`` — Image generation from a text prompt

.. _Bifrost documentation: https://docs.getbifrost.ai/
