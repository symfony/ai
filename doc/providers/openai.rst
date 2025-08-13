OpenAI Provider
===============

The OpenAI provider offers comprehensive support for OpenAI's models including GPT-4, GPT-3.5, DALL-E, 
Whisper, and text embeddings. It also supports Azure OpenAI deployments.

Installation
------------

The OpenAI provider is included with the Platform component:

.. code-block:: terminal

    $ composer require symfony/ai-platform

Configuration
-------------

Direct OpenAI API
~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
                organization: '%env(OPENAI_ORG_ID)%'  # Optional
        agent:
            default:
                platform: 'ai.platform.openai'
                model:
                    class: 'Symfony\AI\Platform\Bridge\OpenAi\Gpt'
                    name: !php/const Symfony\AI\Platform\Bridge\OpenAi\Gpt::GPT_4O

Azure OpenAI
~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            azure:
                gpt_deployment:
                    base_url: '%env(AZURE_OPENAI_ENDPOINT)%'
                    deployment: '%env(AZURE_OPENAI_DEPLOYMENT)%'
                    api_key: '%env(AZURE_OPENAI_KEY)%'
                    api_version: '2024-02-15-preview'

Programmatic Setup
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;

    // Direct OpenAI
    $platform = PlatformFactory::create(
        apiKey: $_ENV['OPENAI_API_KEY'],
        organization: $_ENV['OPENAI_ORG_ID'] // Optional
    );

    // Azure OpenAI
    use Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory as AzureFactory;
    
    $platform = AzureFactory::create(
        endpoint: $_ENV['AZURE_ENDPOINT'],
        deployment: $_ENV['AZURE_DEPLOYMENT'],
        apiKey: $_ENV['AZURE_KEY'],
        apiVersion: '2024-02-15-preview'
    );

Language Models
---------------

Available GPT Models
~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;

    // GPT-4 models
    $gpt4o = new Gpt(Gpt::GPT_4O);           // Most capable, multimodal
    $gpt4oMini = new Gpt(Gpt::GPT_4O_MINI);  // Smaller, faster, cheaper
    $gpt4Turbo = new Gpt(Gpt::GPT_4_TURBO);  // Previous generation

    // GPT-3.5 models
    $gpt35Turbo = new Gpt(Gpt::GPT_35_TURBO); // Fast, cost-effective

    // O1 reasoning models
    $o1 = new Gpt(Gpt::O1);                   // Advanced reasoning
    $o1Mini = new Gpt(Gpt::O1_MINI);          // Faster reasoning model
    $o1Preview = new Gpt(Gpt::O1_PREVIEW);    // Preview version

Basic Chat Completion
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('Explain quantum computing in simple terms')
    );

    $result = $platform->invoke($gpt4o, $messages);
    echo $result->getContent();

Advanced Options
~~~~~~~~~~~~~~~~

.. code-block:: php

    $result = $platform->invoke($model, $messages, [
        'temperature' => 0.7,        // Creativity (0-2, default: 1)
        'max_tokens' => 2000,        // Max response length
        'top_p' => 0.9,              // Nucleus sampling
        'frequency_penalty' => 0.5,  // Reduce repetition
        'presence_penalty' => 0.5,   // Encourage new topics
        'stop' => ['\n\n', 'END'],   // Stop sequences
        'seed' => 12345,             // Deterministic output
        'user' => 'user-123',        // Track users for safety
    ]);

Vision Capabilities
~~~~~~~~~~~~~~~~~~~

Process images with GPT-4 Vision:

.. code-block:: php

    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Content\ImageUrl;

    // Image from file
    $message = Message::ofUser(
        'What is in this image?',
        Image::fromFile('/path/to/image.jpg')
    );

    // Image from URL
    $message = Message::ofUser(
        'Describe this chart',
        new ImageUrl('https://example.com/chart.png')
    );

    // Multiple images
    $message = Message::ofUser(
        'What are the differences between these images?',
        Image::fromFile('/path/to/image1.jpg'),
        Image::fromFile('/path/to/image2.jpg')
    );

    $result = $platform->invoke($gpt4o, new MessageBag($message));

Audio Processing
~~~~~~~~~~~~~~~~

Process audio with GPT-4 audio capabilities:

.. code-block:: php

    use Symfony\AI\Platform\Message\Content\Audio;

    $message = Message::ofUser(
        'What is being said in this recording?',
        Audio::fromFile('/path/to/audio.mp3')
    );

    $result = $platform->invoke($gpt4o, new MessageBag($message));

Tool Calling
~~~~~~~~~~~~

Enable function calling:

.. code-block:: php

    use Symfony\AI\Platform\Tool\Tool;

    $tool = new Tool(
        name: 'get_weather',
        description: 'Get current weather',
        parameters: [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name'
                ]
            ],
            'required' => ['location']
        ]
    );

    $result = $platform->invoke($model, $messages, [
        'tools' => [$tool],
        'tool_choice' => 'auto'  // or 'required', 'none', or specific tool
    ]);

    // Handle tool calls
    foreach ($result->getToolCalls() as $toolCall) {
        echo $toolCall->name;      // 'get_weather'
        echo $toolCall->arguments;  // ['location' => 'Paris']
    }

Structured Output
~~~~~~~~~~~~~~~~~

Get JSON responses with guaranteed structure:

.. code-block:: php

    $result = $platform->invoke($model, $messages, [
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'product_info',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'price' => ['type' => 'number'],
                        'inStock' => ['type' => 'boolean']
                    ],
                    'required' => ['name', 'price', 'inStock']
                ]
            ]
        ]
    ]);

    $data = json_decode($result->getContent(), true);

Streaming
~~~~~~~~~

Stream responses for real-time output:

.. code-block:: php

    $result = $platform->invoke($model, $messages, ['stream' => true]);

    foreach ($result->getContent() as $chunk) {
        echo $chunk; // Output each token as it arrives
        flush();
    }

Embeddings
----------

Text Embeddings Models
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

    // Available models
    $embeddings = new Embeddings(Embeddings::TEXT_3_LARGE);  // Most capable
    $embeddings = new Embeddings(Embeddings::TEXT_3_SMALL);  // Faster, cheaper
    $embeddings = new Embeddings(Embeddings::ADA_002);       // Legacy model

Generate Embeddings
~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    // Single text
    $result = $platform->invoke($embeddings, 'Text to embed');
    $vector = $result->asVectors()[0];
    $data = $vector->getData(); // Array of floats

    // Batch processing
    $texts = [
        'First document',
        'Second document',
        'Third document'
    ];
    
    $result = $platform->invoke($embeddings, $texts);
    $vectors = $result->asVectors(); // Array of Vector objects

    // With dimensions reduction (text-3 models only)
    $result = $platform->invoke($embeddings, $text, [
        'dimensions' => 256  // Reduce from default 1536
    ]);

Image Generation
----------------

DALL-E Models
~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Bridge\OpenAi\DallE;

    // DALL-E 3 (latest)
    $dalle3 = new DallE(DallE::DALL_E_3);

    // DALL-E 2
    $dalle2 = new DallE(DallE::DALL_E_2);

Generate Images
~~~~~~~~~~~~~~~

.. code-block:: php

    // Generate image
    $result = $platform->invoke($dalle3, 'A serene mountain landscape at sunset');

    // Get image data
    $binary = $result->asBinary();
    $imageData = $binary->getContent();
    $mimeType = $binary->getMimeType(); // 'image/png'

    // Save to file
    file_put_contents('generated.png', $imageData);

    // Advanced options
    $result = $platform->invoke($dalle3, $prompt, [
        'size' => '1792x1024',    // Or '1024x1024', '1024x1792'
        'quality' => 'hd',        // Or 'standard'
        'style' => 'vivid',       // Or 'natural'
        'n' => 1,                 // Number of images (1-10 for DALL-E 2)
        'response_format' => 'b64_json'  // Or 'url'
    ]);

Audio Transcription
-------------------

Whisper Model
~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Bridge\OpenAi\Whisper;

    $whisper = new Whisper(Whisper::WHISPER_1);

Transcribe Audio
~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Message\Content\Audio;

    // Transcribe audio file
    $audio = Audio::fromFile('/path/to/audio.mp3');
    $result = $platform->invoke($whisper, $audio);
    $transcription = $result->getContent();

    // With options
    $result = $platform->invoke($whisper, $audio, [
        'language' => 'en',       // Input language (ISO 639-1)
        'prompt' => 'Meeting transcript:',  // Context prompt
        'temperature' => 0,       // Sampling temperature
        'response_format' => 'json'  // Or 'text', 'srt', 'vtt'
    ]);

Token Management
----------------

Count Tokens
~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Bridge\OpenAi\TokenOutputProcessor;

    $processor = new TokenOutputProcessor();
    
    // Get token metadata from result
    $result = $platform->invoke($model, $messages);
    $metadata = $result->getMetadata();
    
    echo $metadata->get('input_tokens');   // Prompt tokens
    echo $metadata->get('output_tokens');  // Completion tokens
    echo $metadata->get('total_tokens');   // Total usage

Error Handling
--------------

Handle OpenAI-specific errors:

.. code-block:: php

    use Symfony\AI\Platform\Exception\ContentFilterException;
    use Symfony\AI\Platform\Exception\RuntimeException;

    try {
        $result = $platform->invoke($model, $messages);
    } catch (ContentFilterException $e) {
        // Content violated OpenAI's usage policies
        echo "Content filtered: " . $e->getMessage();
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'rate_limit')) {
            // Rate limit exceeded
            echo "Rate limited, retry after: " . $e->getRetryAfter();
        } elseif (str_contains($e->getMessage(), 'insufficient_quota')) {
            // API quota exceeded
            echo "Quota exceeded";
        } else {
            // Other API errors
            echo "API error: " . $e->getMessage();
        }
    }

Best Practices
--------------

Model Selection
~~~~~~~~~~~~~~~

* **GPT-4o**: Best for complex reasoning, vision tasks, and when quality matters most
* **GPT-4o Mini**: Good balance of capability and cost for most applications
* **GPT-3.5 Turbo**: Fast and cheap for simple tasks
* **O1 models**: For complex reasoning and problem-solving tasks

Cost Optimization
~~~~~~~~~~~~~~~~~

1. Use appropriate models for each task
2. Set reasonable ``max_tokens`` limits
3. Cache embeddings to avoid recomputation
4. Use batching for bulk operations
5. Implement retry logic with exponential backoff

Performance Tips
~~~~~~~~~~~~~~~~

1. Use streaming for long responses
2. Process requests in parallel when possible
3. Implement caching for repeated queries
4. Use smaller embedding models when appropriate
5. Reduce embedding dimensions when possible

Security
~~~~~~~~

1. Never expose API keys in client-side code
2. Use environment variables for configuration
3. Implement rate limiting in your application
4. Monitor usage and set spending limits
5. Validate and sanitize user inputs

Examples
--------

Complete examples available in the repository:

* Chat completion: ``examples/openai/chat.php``
* Image processing: ``examples/openai/image-input-binary.php``
* Audio transcription: ``examples/openai/audio-transcript.php``
* Tool calling: ``examples/openai/toolcall.php``
* Streaming: ``examples/openai/stream.php``
* Structured output: ``examples/openai/structured-output-math.php``

Next Steps
----------

* Explore other providers: :doc:`anthropic`, :doc:`gemini`
* Learn about tool calling: :doc:`../features/tool-calling`
* Implement RAG: :doc:`../features/rag`
* See configuration options: :doc:`../reference/configuration`