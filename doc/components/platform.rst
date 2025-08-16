Platform Component
==================

The Platform component is the foundation of Symfony AI, providing a unified interface for interacting with 
various AI model providers. It abstracts the complexities of different APIs while maintaining provider-specific 
capabilities.

Overview
--------

The Platform component enables you to:

* Work with multiple AI providers through a single interface
* Switch between providers without changing application code
* Handle different input/output types (text, images, audio, embeddings)
* Stream responses for real-time interactions
* Process requests in parallel for better performance

Basic Usage
-----------

Creating a Platform
~~~~~~~~~~~~~~~~~~~

Each provider has a factory for easy initialization::

    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;

    // Create platform
    $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);

    // Create model
    $model = new Gpt(Gpt::GPT_4O_MINI);

    // Invoke model
    $result = $platform->invoke($model, $messages);

Working with Messages
~~~~~~~~~~~~~~~~~~~~~

Messages represent the conversation between user and AI::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Create a conversation
    $messages = new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('Hello, how are you?'),
        Message::forAssistant('I\'m doing well, thank you!'),
        Message::ofUser('What can you help me with?')
    );

    // Invoke the model
    $result = $platform->invoke($model, $messages);
    echo $result->getContent();

Message Types
-------------

System Messages
~~~~~~~~~~~~~~~

Set the behavior and context for the AI::

    use Symfony\AI\Platform\Message\Message;

    $systemMessage = Message::forSystem(
        'You are a Python expert. Provide code examples when appropriate.'
    );

User Messages
~~~~~~~~~~~~~

Represent user input with optional multimodal content::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Content\Audio;

    // Text only
    $textMessage = Message::ofUser('Explain quantum computing');

    // With image
    $imageMessage = Message::ofUser(
        'What is in this image?',
        Image::fromFile('/path/to/image.jpg')
    );

    // With audio
    $audioMessage = Message::ofUser(
        'Transcribe this audio',
        Audio::fromFile('/path/to/audio.mp3')
    );

    // Multiple content items
    $multiMessage = Message::ofUser(
        'Compare these images',
        Image::fromFile('/path/to/image1.jpg'),
        Image::fromFile('/path/to/image2.jpg')
    );

Assistant Messages
~~~~~~~~~~~~~~~~~~

Represent AI responses::

    $assistantMessage = Message::forAssistant('Here is my response');

    // With tool calls
    $toolCallMessage = Message::forAssistant(
        content: 'I\'ll check the weather for you',
        toolCalls: [
            new ToolCall('weather_tool', ['location' => 'Paris'])
        ]
    );

Tool Call Messages
~~~~~~~~~~~~~~~~~~

Represent tool execution results::

    use Symfony\AI\Platform\Message\ToolCallMessage;

    $toolResult = new ToolCallMessage(
        toolCallId: 'call_123',
        content: json_encode(['temperature' => 22, 'condition' => 'sunny'])
    );

Models and Capabilities
-----------------------

Model Configuration
~~~~~~~~~~~~~~~~~~~

Models define the AI's capabilities and configuration::

    use Symfony\AI\Platform\Model;
    use Symfony\AI\Platform\Capability;

    // Using predefined models
    $gpt = new Gpt(Gpt::GPT_4O);
    $claude = new Claude(Claude::SONNET_37);

    // Custom model
    $customModel = new Model(
        name: 'custom-model',
        capabilities: [
            Capability::LANGUAGE_MODEL,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_JSON
        ],
        options: [
            'temperature' => 0.7,
            'max_tokens' => 2000
        ]
    );

Checking Capabilities
~~~~~~~~~~~~~~~~~~~~~

::

    if ($model->hasCapability(Capability::INPUT_IMAGE)) {
        // Model supports image input
    }

    if ($model->hasCapability(Capability::TOOL_CALLING)) {
        // Model supports function calling
    }

Available capabilities:

* ``LANGUAGE_MODEL`` - Text generation
* ``EMBEDDINGS`` - Vector embeddings
* ``INPUT_IMAGE`` - Process images
* ``INPUT_AUDIO`` - Process audio
* ``OUTPUT_IMAGE`` - Generate images
* ``OUTPUT_JSON`` - Structured JSON output
* ``TOOL_CALLING`` - Function/tool calling
* ``STREAMING`` - Stream responses

Results and Processing
----------------------

Result Types
~~~~~~~~~~~~

Different models return different result types::

    use Symfony\AI\Platform\Result\TextResult;
    use Symfony\AI\Platform\Result\VectorResult;
    use Symfony\AI\Platform\Result\BinaryResult;
    use Symfony\AI\Platform\Result\ToolCallResult;

    // Text generation
    $textResult = $platform->invoke($languageModel, $messages);
    echo $textResult->getContent(); // String content

    // Embeddings
    $vectorResult = $platform->invoke($embeddingModel, 'Text to embed');
    $vectors = $vectorResult->asVectors(); // Array of Vector objects

    // Image generation
    $binaryResult = $platform->invoke($imageModel, 'A sunset over mountains');
    $imageData = $binaryResult->asBinary(); // Binary content

    // Tool calls
    $toolResult = $platform->invoke($model, $messages);
    $toolCalls = $toolResult->getToolCalls(); // Array of ToolCall objects

Accessing Metadata
~~~~~~~~~~~~~~~~~~

Results include metadata about the generation::

    $result = $platform->invoke($model, $messages);

    // Token usage
    $metadata = $result->getMetadata();
    echo $metadata->get('input_tokens');  // Tokens in prompt
    echo $metadata->get('output_tokens'); // Tokens in response
    echo $metadata->get('total_tokens');  // Total tokens used

    // Model information
    echo $metadata->get('model');         // Model used
    echo $metadata->get('finish_reason'); // Why generation stopped

Streaming Responses
-------------------

Stream responses for real-time output::

    $result = $platform->invoke($model, $messages, ['stream' => true]);

    // Check if streaming
    if ($result instanceof StreamResult) {
        foreach ($result->getContent() as $chunk) {
            echo $chunk; // Output each chunk as it arrives
            flush();
        }
    }

Multimodal Input
----------------

Images
~~~~~~

::

    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Content\ImageUrl;

    // From file
    $image = Image::fromFile('/path/to/image.jpg');

    // From data URL
    $image = Image::fromDataUrl('data:image/png;base64,iVBORw0...');

    // From URL
    $image = new ImageUrl('https://example.com/image.jpg');

    // Use in message
    $message = Message::ofUser('Describe this image', $image);

Audio
~~~~~

::

    use Symfony\AI\Platform\Message\Content\Audio;

    // From file
    $audio = Audio::fromFile('/path/to/audio.mp3');

    // Use in message
    $message = Message::ofUser('Transcribe this audio', $audio);

Documents
~~~~~~~~~

::

    use Symfony\AI\Platform\Message\Content\Document;
    use Symfony\AI\Platform\Message\Content\DocumentUrl;

    // From file
    $document = Document::fromFile('/path/to/document.pdf');

    // From URL
    $document = new DocumentUrl('https://example.com/document.pdf');

    // Use in message
    $message = Message::ofUser('Summarize this document', $document);

Embeddings
----------

Generate vector embeddings for semantic search::

    use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

    $embeddings = new Embeddings(Embeddings::TEXT_3_SMALL);

    // Single text
    $result = $platform->invoke($embeddings, 'Text to embed');
    $vector = $result->asVectors()[0];
    $data = $vector->getData(); // Array of floats

    // Multiple texts (batch processing)
    $texts = ['First text', 'Second text', 'Third text'];
    $result = $platform->invoke($embeddings, $texts);
    $vectors = $result->asVectors(); // Array of Vector objects

Parallel Processing
-------------------

Process multiple requests concurrently::

    // Prepare multiple invocations
    $results = [];
    foreach ($prompts as $prompt) {
        $messages = new MessageBag(Message::ofUser($prompt));
        $results[] = $platform->invoke($model, $messages);
    }

    // Results are processed in parallel automatically
    foreach ($results as $result) {
        echo $result->getContent() . PHP_EOL;
    }

Error Handling
--------------

Handle platform-specific errors::

    use Symfony\AI\Platform\Exception\ContentFilterException;
    use Symfony\AI\Platform\Exception\RuntimeException;

    try {
        $result = $platform->invoke($model, $messages);
    } catch (ContentFilterException $e) {
        // Content violated provider's content policy
        echo "Content filtered: " . $e->getMessage();
    } catch (RuntimeException $e) {
        // API error (rate limit, network, etc.)
        echo "API error: " . $e->getMessage();
    }

Platform Options
----------------

Configure platform behavior::

    $result = $platform->invoke($model, $messages, [
        // Model parameters
        'temperature' => 0.8,        // Randomness (0-2)
        'max_tokens' => 1000,        // Maximum response length
        'top_p' => 0.9,              // Nucleus sampling
        'frequency_penalty' => 0.5,  // Reduce repetition
        'presence_penalty' => 0.5,   // Encourage new topics
        
        // Response format
        'stream' => true,            // Stream response
        'response_format' => [       // JSON mode
            'type' => 'json_object'
        ],
        
        // System behavior
        'seed' => 12345,             // Deterministic output
        'user' => 'user-123',        // User identifier
    ]);

Testing
-------

Use the InMemoryPlatform for testing::

    use Symfony\AI\Platform\InMemoryPlatform;
    use Symfony\AI\Platform\Result\VectorResult;
    use Symfony\AI\Platform\Vector\Vector;

    // Fixed response
    $platform = new InMemoryPlatform('Test response');

    // Dynamic response
    $platform = new InMemoryPlatform(
        fn($model, $input, $options) => "Echo: {$input}"
    );

    // Custom result types
    $platform = new InMemoryPlatform(
        fn() => new VectorResult(new Vector([0.1, 0.2, 0.3]))
    );

Next Steps
----------

* Learn about specific providers: :doc:`../providers/openai`
* Build AI agents: :doc:`agent`
* Implement RAG: :doc:`store`
* Explore examples: :doc:`../resources/examples`