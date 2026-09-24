Venice AI
=========

Venice AI is an open and permissionless AI platform that exposes a wide range of models for chat completion (with
function calling, vision, reasoning and structured outputs), embeddings, text-to-speech, speech-to-text, image
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

    $result = $platform->invoke('venice-uncensored-1-2', $messages);

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

    $result = $platform->invoke('venice-uncensored-1-2', $messages, [
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
        'venice-uncensored-1-2',
        new MessageBag(Message::ofUser('Weather in Paris?')),
        ['tools' => $tools],
    );

Structured Outputs
~~~~~~~~~~~~~~~~~~

Pass a JSON Schema in ``response_format`` to force a structured response::

    $result = $platform->invoke('venice-uncensored-1-2', $messages, [
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

    foreach ($result->asStream() as $chunk) {
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

    $result = $platform->invoke('venice-uncensored-1-2', $messages, [
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

Venice exposes editing models under its own ``inpaint`` type and the upscaler under ``upscale``; the bridge maps both
to the ``image-to-image`` capability and routes them to ``/image/edit`` by default. Use the ``mode`` option to switch
to ``upscale`` or ``background-remove``. The image itself is passed as :class:`Symfony\\AI\\Platform\\Message\\Content\\Image`,
or as a raw ``image`` string holding a base64 payload, a data URL or an HTTP URL::

    use Symfony\AI\Platform\Message\Content\Image;

    // Edit
    $result = $platform->invoke('firered-image-edit', Image::fromFile('/path/to/in.png'), [
        'prompt' => 'Make it sepia',
    ]);
    $result->asFile('/tmp/edited.png');

    // Upscale
    $result = $platform->invoke('upscaler', Image::fromFile('/path/to/in.png'), [
        'mode' => 'upscale',
        'scale' => 2,
    ]);
    $result->asFile('/tmp/upscaled.png');

    // Background removal - the endpoint takes no model, so any image-to-image model selects it
    $result = $platform->invoke('upscaler', ['image' => 'https://example.com/in.png'], [
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

Speech-to-Text
~~~~~~~~~~~~~~

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

Generate videos from text prompts, from a source image or from a source video (passed as ``video_url``). The video API
is queue-based, and so is the bridge: the invocation queues the generation and returns a
:class:`Symfony\\AI\\Platform\\Result\\JobResult` carrying the handle of that queue entry, rather than waiting for a video
that takes minutes. See :ref:`Asynchronous Jobs <platform-asynchronous-jobs>` for what a handle is good for::

    use Symfony\AI\Platform\Bridge\Venice\Factory;
    use Symfony\AI\Platform\Job\JobRunner;
    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = Factory::createPlatform($_ENV['VENICE_API_KEY'], httpClient: $httpClient);

    // `duration` and `aspect_ratio` are required, and the accepted values differ per model, as does
    // `resolution`; `GET /models` reports all three under `model_spec.constraints`. Generation is
    // billed per pixel-second, so a short clip at a low resolution costs a fraction of a long one.
    $handle = $platform->invoke('pixverse-c1-text-to-video', new Text('A timelapse of a sunset over a mountain range'), [
        'duration' => '3s',
        'aspect_ratio' => '16:9',
        'resolution' => '360p',
    ])->asJob();

    // Waiting is the caller's decision, and the job client needs no platform of its own.
    $jobClient = Factory::createJobClient($_ENV['VENICE_API_KEY'], httpClient: $httpClient);
    (new JobRunner())->wait($jobClient, $handle)->asFile('/path/to/sunset.mp4');

    // Image-to-video. This model derives the aspect ratio from the source image and rejects
    // `aspect_ratio`, which its empty `aspect_ratios` constraint announces.
    $handle = $platform->invoke('pixverse-c1-image-to-video', Image::fromFile('/path/to/mountain.jpg'), [
        'prompt' => 'Camera slowly zooms in',
        'duration' => '3s',
        'resolution' => '360p',
    ])->asJob();

    (new JobRunner())->wait($jobClient, $handle)->asFile('/path/to/zoom.mp4');

The bridge passes options through untouched and injects no defaults of its own, so a model that
rejects a field never receives one it did not ask for.

Model Catalog
~~~~~~~~~~~~~

Unlike most other bridges, Venice uses a dynamic model catalog. The available models and their capabilities are fetched
at runtime from the Venice API (``GET /models``) and cached for the lifetime of the catalog. This means the bridge
automatically supports new models as they become available on the platform, without requiring code changes.

Capabilities are derived from the ``type`` the API reports for a model (``text``, ``embedding``, ``image``, ``inpaint``,
``upscale``, ``tts``, ``asr``, ``music``, ``video``), from the feature flags a text model carries under
``model_spec.capabilities``, and from ``model_spec.constraints.model_type`` for a video model. A type the bridge has no
capability for leaves the model listed without capabilities rather than hiding it.

The catalog also exposes Venice's "trait" aliases (``default``, ``most_intelligent``, ``default_reasoning``,
``default_vision``, ``default_code``, ``most_uncensored``, ``function_calling_default``, ``fastest``)::

    use Symfony\AI\Platform\Bridge\Venice\ModelCatalog;

    /** @var ModelCatalog $catalog */
    $modelId = $catalog->resolveTrait('default_reasoning');

Examples
--------

See the ``examples/venice/`` directory for complete working examples:

* ``chat.php`` - Basic chat completion
* ``stream.php`` - Streaming chat completion
* ``vision.php`` - Vision (image input)
* ``toolcall.php`` - Function calling
* ``web-search.php`` - Web search via ``venice_parameters``
* ``chat-with-character.php`` - Character roleplay via ``venice_parameters``
* ``embeddings.php`` - Text embeddings
* ``text-to-image.php`` - Image generation from a text prompt
* ``image-editing.php`` - Image edition
* ``image-upscale.php`` - Image upscaling
* ``text-to-speech.php`` - Text-to-speech conversion
* ``speech-to-text.php`` - Audio transcription
* ``text-to-video.php`` - Video generation from a text prompt
* ``image-to-video.php`` - Video generation from an image

.. _Venice AI API reference: https://docs.venice.ai/api-reference/api-spec
.. _Venice AI dashboard: https://venice.ai/settings/api
