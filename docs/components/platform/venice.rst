Venice AI
=========

Venice AI is an open and permissionless AI platform that exposes a wide range of models for chat completion (with
function calling, vision, reasoning and structured outputs), embeddings, text-to-speech, speech recognition, image
generation, image edition, image upscaling and video generation. The bridge supports all of those capabilities through
a unified interface, plus the Venice-specific extensions (``venice_parameters`` for web search, character roleplay,
private TEE inference, etc.).

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

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('What is the capital of France?'),
    );

    $result = $platform->invoke('venice-uncensored', $messages);

    echo $result->asText();

Streaming
~~~~~~~~~

Chat completions can be streamed by passing the ``stream`` option. Token usage is automatically requested from the
API at the end of the stream and yielded as the last chunk::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

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

Vision (Image Input)
~~~~~~~~~~~~~~~~~~~~

Models exposing the ``input-image`` capability accept image content in the user message. The bridge converts
``Message\Content\Image`` instances into the OpenAI-compatible ``image_url`` payload expected by Venice::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $messages = new MessageBag(
        Message::ofUser(
            'Describe this image',
            Image::fromFile('/path/to/photo.jpg'),
        ),
    );

    $result = $platform->invoke('qwen3-vl-235b-a22b', $messages);

    echo $result->asText();

Function / Tool Calling
~~~~~~~~~~~~~~~~~~~~~~~

Venice supports OpenAI-compatible function calling on models exposing the ``tool-calling`` capability. Tools registered
through the Agent component are automatically translated. When invoking the Platform directly, pass them in
``$options['tools']`` and a ``ToolCallResult`` is returned when the model decides to call a tool::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'description' => 'Get the current weather for a city',
            'parameters' => [
                'type' => 'object',
                'properties' => ['city' => ['type' => 'string']],
                'required' => ['city'],
            ],
        ],
    ]];

    $result = $platform->invoke(
        'venice-uncensored',
        new MessageBag(Message::ofUser('Weather in Paris?')),
        ['tools' => $tools],
    );

Structured Outputs
~~~~~~~~~~~~~~~~~~

Pass a JSON Schema in ``response_format`` to force a structured response::

    $result = $platform->invoke('venice-uncensored', $messages, [
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'extracted_entities',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'people' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ],
    ]);

Reasoning / Thinking
~~~~~~~~~~~~~~~~~~~~

Models with the ``thinking`` capability (e.g. ``qwen3-235b-a22b-thinking-2507``) expose a ``reasoning_effort`` option
(``minimal``/``low``/``medium``/``high``/``max``) and emit ``ThinkingDelta`` chunks in streaming responses. Reasoning
tokens are also exposed via ``TokenUsage::getThinkingTokens()``::

    $result = $platform->invoke('qwen3-235b-a22b-thinking-2507', $messages, [
        'reasoning_effort' => 'high',
        'stream' => true,
    ]);

    foreach ($result->getContent() as $chunk) {
        if ($chunk instanceof Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta) {
            echo '[thinking] '.$chunk->getThinking();
        }
    }

Venice-Specific Parameters
~~~~~~~~~~~~~~~~~~~~~~~~~~

Venice extends the OpenAI chat API with a ``venice_parameters`` object — covering web search modes, public character
roleplay, X/Twitter search, end-to-end encryption (TEE) and thinking control. The bridge ships a typed
``VeniceParameters`` builder that takes care of serialization::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Bridge\Venice\VeniceParameters;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('venice-uncensored', $messages, [
        'venice_parameters' => new VeniceParameters(
            characterSlug: 'alan-watts',
            enableWebSearch: VeniceParameters::WEB_SEARCH_AUTO,
            enableWebCitations: true,
            stripThinkingResponse: true,
        ),
    ]);

Plain arrays are also accepted, e.g. ``['venice_parameters' => ['enable_web_search' => 'on']]``.

Text Embeddings
~~~~~~~~~~~~~~~

Generate vector embeddings from text. The encoding format defaults to ``float`` and can be overridden, along with
``dimensions`` for truncation::

    use Symfony\AI\Platform\Bridge\Venice\Factory;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('text-embedding-bge-m3', 'The quick brown fox jumps over the lazy dog.', [
        'dimensions' => 512,
        'encoding_format' => 'float',
    ]);

    $vector = $result->asVectors()[0];
    echo 'Dimensions: '.$vector->getDimensions();

Image Generation
~~~~~~~~~~~~~~~~

Generate images from text prompts. The result is returned as binary data (base64 decoded). All Venice options
(``negative_prompt``, ``aspect_ratio``, ``resolution``, ``cfg_scale``, ``steps``, ``style_preset``,
``safe_mode``, ``hide_watermark``, ``variants``, ``embed_exif_metadata``, ``enable_web_search``…) can be passed as
options::

    use Symfony\AI\Platform\Bridge\Venice\Factory;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('z-image-turbo', 'A beautiful sunset over a mountain range', [
        'aspect_ratio' => '16:9',
        'cfg_scale' => 7.5,
        'safe_mode' => false,
    ]);

    $result->asFile('/path/to/image.png');

When the model returns multiple variants, a :class:`Symfony\\AI\\Platform\\Result\\ChoiceResult` is returned instead.

Image Edition / Upscale / Background Removal
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Models with the ``image-to-image`` capability route to ``/image/edit`` by default. Use the ``mode`` option to switch to
``upscale`` or ``background-remove``::

    // Edit
    $result = $platform->invoke('firered-image-edit', [
        'image' => 'https://example.com/in.png',
        'prompt' => 'Make it sepia',
    ]);
    $result->asFile('/tmp/edited.png');

    // Upscale
    $result = $platform->invoke('upscaler', ['image' => 'data:image/png;base64,...'], [
        'mode' => 'upscale',
        'scale' => 2,
    ]);
    $result->asFile('/tmp/upscaled.png');

    // Background removal
    $result = $platform->invoke('bria-bg-remover', ['image' => 'https://example.com/in.png'], [
        'mode' => 'background-remove',
    ]);
    $result->asFile('/tmp/transparent.png');

Text-to-Speech
~~~~~~~~~~~~~~

Convert text to audio. ``voice`` and ``response_format`` (``mp3``, ``opus``, ``aac``, ``flac``, ``wav``, ``pcm``) are
configurable through options. Use a voice handle prefixed with ``vv_`` for voice cloning::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('tts-kokoro', new Text('Hello world from Venice'), [
        'voice' => 'af_sky',
        'response_format' => 'wav',
        'speed' => 1.1,
    ]);

    echo $result->asBinary();

Speech Recognition
~~~~~~~~~~~~~~~~~~

Transcribe audio files to text. ``language`` (ISO 639-1) and ``timestamps`` are configurable::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Message\Content\Audio;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    $result = $platform->invoke('nvidia/parakeet-tdt-0.6b-v3', Audio::fromFile('/path/to/audio.mp3'), [
        'language' => 'en',
        'timestamps' => true,
    ]);

    echo $result->asText();

Video Generation
~~~~~~~~~~~~~~~~

Generate videos from text prompts, from a source image (``image_url``) or from a source video (``video_url``). The
video API is queue-based: the bridge polls ``/video/retrieve`` until the video is ready, then returns the binary
content. Polling can be tuned via ``max_polling_attempts`` and ``polling_interval_seconds``::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    // Text-to-video
    $result = $platform->invoke('seedance-1-5-pro-text-to-video', new Text('A timelapse of a sunset over a mountain range'), [
        'aspect_ratio' => '16:9',
        'duration' => '8s',
        'max_polling_attempts' => 180,
    ]);
    $result->asFile('/path/to/sunset.mp4');

    // Image-to-video
    $result = $platform->invoke('seedance-1-5-pro-image-to-video', [
        'prompt' => 'Camera slowly zooms in',
        'image_url' => 'https://example.com/mountain.jpg',
    ]);
    $result->asFile('/path/to/zoom.mp4');

    // Video-to-video (e.g. Runway Gen-4 Aleph)
    $result = $platform->invoke('runway-gen4-aleph', [
        'prompt' => 'Restyle as anime',
        'video_url' => 'https://example.com/source.mp4',
    ]);
    $result->asFile('/path/to/anime.mp4');

Model Catalog
~~~~~~~~~~~~~

Unlike most other bridges, Venice uses a dynamic model catalog. The available models and their capabilities are fetched
at runtime from the Venice API (``GET /models``). This means the bridge automatically supports new models as they become
available on the platform, without requiring code changes.

The catalog also exposes Venice's "trait" aliases (``default``, ``most_intelligent``, ``default_reasoning``,
``default_vision``, ``default_code``, ``most_uncensored``, ``function_calling_default``, ``fastest``)::

    use Symfony\AI\Platform\Bridge\Venice\ModelCatalog;

    /** @var ModelCatalog $catalog */
    $modelId = $catalog->resolveTrait('default_reasoning');

Examples
--------

See the ``examples/venice/`` directory for complete working examples:

* ``chat.php`` - Basic chat completion
* ``chat-as-stream.php`` - Streaming chat completion
* ``chat-with-vision.php`` - Vision (image input)
* ``chat-with-tools.php`` - Function calling
* ``chat-with-web-search.php`` - Web search via ``venice_parameters``
* ``chat-with-character.php`` - Character roleplay via ``venice_parameters``
* ``embeddings.php`` - Text embeddings
* ``image-generation.php`` - Image generation from a text prompt
* ``image-edit.php`` - Image edition
* ``image-upscale.php`` - Image upscaling
* ``text-to-speech.php`` - Text-to-speech conversion
* ``speech-to-text.php`` - Audio transcription
* ``video-generation.php`` - Video generation from a text prompt
* ``video-generation-from-image.php`` - Video generation from an image
* ``video-to-video.php`` - Video-to-video transformation

.. _Venice AI API reference: https://docs.venice.ai/api-reference/api-spec
.. _Venice AI dashboard: https://venice.ai/settings/api
