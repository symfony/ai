Symfony AI - Platform Component
===============================

The Platform component provides an abstraction for interacting with different
models, their providers and contracts.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-platform

Purpose
-------

The Platform component provides a unified interface for working with various AI models, hosted and run by different
providers. It allows developers to easily switch between different AI models and providers without changing their
application code. This is particularly useful for applications that require flexibility in choosing AI models based on
specific use cases or performance requirements.

Usage
-----

The instantiation of the :class:`Symfony\\AI\\Platform\Platform` class is
usually delegated to a provider-specific factory, with a provider being
OpenAI, Anthropic, Google, Replicate, and others.

For example, to use the OpenAI provider, you would typically do something like this::

    use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;

    $platform = PlatformFactory::create(env('OPENAI_API_KEY'));

With this :class:`Symfony\\AI\\Platform\PlatformInterface` instance you can now interact with the LLM::

    // Generate a vector embedding for a text, returns a Symfony\AI\Platform\Result\VectorResult
    $vectorResult = $platform->invoke($embeddings, 'What is the capital of France?');

    // Generate a text completion with GPT, returns a Symfony\AI\Platform\Result\TextResult
    $result = $platform->invoke('gpt-4o-mini', new MessageBag(Message::ofUser('What is the capital of France?')));

Depending on the model and its capabilities, different types of inputs and outputs are supported, which results in a
very flexible and powerful interface for working with AI models.

Models
------

The component provides a model base class :class:`Symfony\\AI\\Platform\\Model` which is a combination of a model name, a set of
capabilities, and additional options. Usually, bridges to specific providers extend this base class to provide a quick
start for vendor-specific models and their capabilities.

Capabilities are a list of strings defined by :class:`Symfony\\AI\\Platform\\Capability`, which can be used to check if a model
supports a specific feature, like ``Capability::INPUT_AUDIO`` or ``Capability::OUTPUT_IMAGE``.

Options are additional parameters that can be passed to the model, like ``temperature`` or ``max_tokens``, and are
usually defined by the specific models and their documentation.

Model Size Variants
~~~~~~~~~~~~~~~~~~~

For providers like Ollama, you can specify model size variants using a colon notation (e.g., ``qwen3:32b``, ``llama3:7b``).
If the exact model name with size variant is not found in the catalog, the system will automatically fall back to the base
model name (``qwen3``, ``llama3``) and use its capabilities while preserving the full model name for the provider.

You can also combine size variants with query parameters::

    use Symfony\AI\Platform\Bridge\Ollama\ModelCatalog;

    $catalog = new ModelCatalog();

    // Get model with size variant
    $model = $catalog->getModel('qwen3:32b');

    // Get model with size variant and query parameters
    $model = $catalog->getModel('qwen3:32b?temperature=0.5&top_p=0.9');

Supported Models & Platforms
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

* **Language Models**
    * `OpenAI's GPT`_ with `OpenAI`_ and `Azure`_ as Platform
    * `Anthropic's Claude`_ with `Anthropic`_ and `AWS Bedrock`_ as Platform
    * `Meta's Llama`_ with `Azure`_, `Ollama`_, `Replicate`_ and `AWS Bedrock`_ as Platform
    * `Gemini`_ with `Google`_, `Vertex AI`_ and `OpenRouter`_ as Platform
    * `Vertex AI Gen AI`_ with `Vertex AI`_ as Platform
    * `DeepSeek's R1`_ with `OpenRouter`_ as Platform
    * `Amazon's Nova`_ with `AWS Bedrock`_ as Platform
    * `Mistral's Mistral`_ with `Mistral`_ as Platform
    * `Albert API`_ models with `Albert`_ as Platform (French government's sovereign AI gateway)
* **Embeddings Models**
    * `Gemini Text Embeddings`_ with `Google`_
    * `Vertex AI Text Embeddings`_ with `Vertex AI`_
    * `OpenAI's Text Embeddings`_ with `OpenAI`_ and `Azure`_ as Platform
    * `Voyage's Embeddings`_ with `Voyage`_ as Platform
    * `Mistral Embed`_ with `Mistral`_ as Platform
* **Other Models**
    * `OpenAI's Dall·E`_ with `OpenAI`_ as Platform
    * `OpenAI's Whisper`_ with `OpenAI`_ and `Azure`_ as Platform
    * `LM Studio Catalog`_ and `HuggingFace`_ Models  with `LM Studio`_ as Platform.
    * All models provided by `HuggingFace`_ can be listed with a command in the examples folder,
      and also filtered, e.g. ``php examples/huggingface/_model-listing.php --provider=hf-inference --task=object-detection``
* **Voice Models**
    * `Cartesia TTS` with `Cartesia`_ as Platform
    * `Cartesia STT` with `Cartesia`_ as Platform

Options
-------

The third parameter of the :method:`Symfony\\AI\\Platform\\PlatformInterface::invoke`
method is an array of options, which basically wraps the options of the corresponding
model and platform, like ``temperature`` or ``max_tokens``::

    $result = $platform->invoke('gpt-4o-mini', $input, [
        'temperature' => 0.7,
        'max_tokens' => 100,
    ]);

.. note::

    For model- and platform-specific options, please refer to the respective documentation.

Language Models and Messages
----------------------------

One central feature of the Platform component is the support for language
models and easing the interaction with them. This is supported by providing
an extensive set of data classes around the concept of messages and their content.

Messages can be of different types, most importantly :class:`Symfony\\AI\\Platform\\Message\\UserMessage`, :class:`Symfony\\AI\\Platform\\Message\\SystemMessage`, or :class:`Symfony\\AI\\Platform\\Message\\AssistantMessage`, can
have different content types, like :class:`Symfony\\AI\\Platform\\Message\\Content\\Text`, :class:`Symfony\\AI\\Platform\\Message\\Content\\Image` or :class:`Symfony\\AI\\Platform\\Message\\Content\\Audio`, and can be grouped into a :class:`Symfony\\AI\\Platform\\Message\\MessageBag`::

    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Create a message bag with a user message
    $messageBag = new MessageBag(
        Message::forSystem('You are a helpful assistant.')
        Message::ofUser('Please describe this picture?', Image::fromFile('/path/to/image.jpg')),
    );

Message Unique IDs
~~~~~~~~~~~~~~~~~~

Each message automatically receives a unique identifier (UUID v7) upon creation.
This provides several benefits:

- **Traceability**: Track individual messages through your application
- **Time-ordered**: UUIDs are naturally sortable by creation time
- **Timestamp extraction**: Get the exact creation time from the ID
- **Database-friendly**: Sequential nature improves index performance

::

    use Symfony\AI\Platform\Message\Message;

    $message = Message::ofUser('Hello, AI!');

    // Access the unique ID
    $id = $message->getId(); // Returns Symfony\Component\Uid\Uuid instance

    // Extract creation timestamp
    $createdAt = $id->getDateTime(); // Returns \DateTimeImmutable
    echo $createdAt->format('Y-m-d H:i:s.u'); // e.g., "2025-06-29 15:30:45.123456"

    // Get string representation
    echo $id->toRfc4122(); // e.g., "01928d1f-6f2e-7123-a456-123456789abc"

Result Streaming
----------------

Since LLMs usually generate a result word by word, most of them also support streaming the result using Server Side
Events. Symfony AI supports that by abstracting the conversion and returning a :class:`Generator` as content of the result::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Message\Message;
    use Symfony\AI\Message\MessageBag;

    // Initialize Platform and LLM

    $agent = new Agent($model);
    $messages = new MessageBag(
        Message::forSystem('You are a thoughtful philosopher.'),
        Message::ofUser('What is the purpose of an ant?'),
    );
    $result = $agent->call($messages, [
        'stream' => true, // enable streaming of response text
    ]);

    foreach ($result->getContent() as $word) {
        echo $word;
    }

.. note::

    To be able to use streaming in your web application,
    an additional layer like `Mercure`_ is needed.

Code Examples
~~~~~~~~~~~~~

* `Streaming Claude`_
* `Streaming GPT`_
* `Streaming Mistral`_

Image Processing
----------------

Some LLMs also support images as input, which Symfony AI supports as content type within the :class:`Symfony\\AI\\Platform\\Message\\UserMessage`::

    use Symfony\AI\Platform\Message\Content\Image;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Initialize Platform, LLM & agent

    $messages = new MessageBag(
        Message::forSystem('You are an image analyzer bot that helps identify the content of images.'),
        Message::ofUser(
            'Describe the image as a comedian would do it.',
            Image::fromFile(dirname(__DIR__).'/tests/fixtures/image.jpg'), // Path to an image file
            Image::fromDataUrl('data:image/png;base64,...'), // Data URL of an image
            new ImageUrl('https://foo.com/bar.png'), // URL to an image
        ),
    );
    $result = $agent->call($messages);

Code Examples
~~~~~~~~~~~~~

* `Binary Image Input with GPT`_
* `Image URL Input with GPT`_

Audio Processing
----------------

Similar to images, some LLMs also support audio as input, which is just another content type within the
:class:`Symfony\\AI\\Platform\\Message\\UserMessage`::

    use Symfony\AI\Platform\Message\Content\Audio;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Initialize Platform, LLM & agent

    $messages = new MessageBag(
        Message::ofUser(
            'What is this recording about?',
            Audio::fromFile('/path/audio.mp3'), // Path to an audio file
        ),
    );
    $result = $agent->call($messages);

Code Examples
~~~~~~~~~~~~~

* `Audio Input with GPT`_

Embeddings
----------

Creating embeddings of word, sentences, or paragraphs is a typical use case around the interaction with LLMs.

The standalone usage results in a :class:`Symfony\\AI\\Store\\Vector` instance::

    use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

    // Initialize platform

    $vectors = $platform->invoke('text-embedding-3-small', $textInput)->asVectors();

    dump($vectors[0]->getData()); // returns something like: [0.123, -0.456, 0.789, ...]

Code Examples
~~~~~~~~~~~~~

* `Embeddings with OpenAI`_
* `Embeddings with Voyage`_
* `Embeddings with Mistral`_

Structured Output
-----------------

A typical use-case of LLMs is to classify and extract data from unstructured sources, which is supported by some models
by features like Structured Output or providing a Response Format.

PHP Classes as Output
~~~~~~~~~~~~~~~~~~~~~

Symfony AI supports that use-case by abstracting the hustle of defining and providing schemas to the LLM and converting
the result back to PHP objects.

To achieve this, the ``Symfony\AI\Platform\StructuredOutput\PlatformSubscriber`` needs to be registered with the platform::

    use Symfony\AI\Fixtures\StructuredOutput\MathReasoning;
    use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
    use Symfony\Component\EventDispatcher\EventDispatcher;

    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new PlatformSubscriber());

    $platform = PlatformFactory::create($apiKey, eventDispatcher: $dispatcher);
    $messages = new MessageBag(
        Message::forSystem('You are a helpful math tutor. Guide the user through the solution step by step.'),
        Message::ofUser('how can I solve 8x + 7 = -23'),
    );
    $result = $platform->invoke('mistral-small-latest', $messages, ['output_structure' => MathReasoning::class]);

    dump($result->asObject()); // returns an instance of `MathReasoning` class

Array Structures as Output
~~~~~~~~~~~~~~~~~~~~~~~~~~

Also PHP array structures as response_format are supported, which also requires the event subscriber mentioned above. On
top this example uses the feature through the agent to leverage tool calling::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Initialize Platform, LLM and agent with processors and Clock tool

    $messages = new MessageBag(Message::ofUser('What date and time is it?'));
    $result = $agent->call($messages, ['response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'clock',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'description' => 'The current date in the format YYYY-MM-DD.'],
                    'time' => ['type' => 'string', 'description' => 'The current time in the format HH:MM:SS.'],
                ],
                'required' => ['date', 'time'],
                'additionalProperties' => false,
            ],
        ],
    ]]);

    dump($result->getContent()); // returns an array

Code Examples
~~~~~~~~~~~~~

* `Structured Output with PHP class`_
* `Structured Output with array`_

Server Tools
------------

Some platforms provide built-in server-side tools for enhanced capabilities without custom implementations:

* :doc:`platform/gemini-server-tools` - URL Context, Google Search, Code Execution
* :doc:`platform/vertexai-server-tools` - URL Context, Google Search, Code Execution

For complete Vertex AI setup and usage guide, see :doc:`platform/vertexai`.

Parallel Platform Calls
-----------------------

Since the ``Platform`` sits on top of Symfony's HttpClient component, it supports multiple model calls in parallel,
which can be useful to speed up the processing::

    // Initialize Platform

    foreach ($inputs as $input) {
        $results[] = $platform->invoke('gpt-4o-mini', $input);
    }

    foreach ($results as $result) {
        echo $result->asText().PHP_EOL;
    }

Testing Tools
-------------

For unit or integration testing, you can use the :class:`Symfony\\AI\\Platform\\InMemoryPlatform`,
which implements :class:`Symfony\\AI\\Platform\\PlatformInterface` without calling external APIs.

It supports returning either:

- A fixed string result
- A callable that dynamically returns a simple string or any :class:`Symfony\\AI\\Platform\\Result\\ResultInterface` based on the model, input, and options::

    use Symfony\AI\Platform\InMemoryPlatform;
    use Symfony\AI\Platform\Model;

    $platform = new InMemoryPlatform('Fake result');

    $result = $platform->invoke('gpt-4o-mini', 'What is the capital of France?');

    echo $result->asText(); // "Fake result"

Dynamic Text Results
~~~~~~~~~~~~~~~~~~~~

::

    $platform = new InMemoryPlatform(
        fn($model, $input, $options) => "Echo: {$input}"
    );

    $result = $platform->invoke('gpt-4o-mini', 'Hello AI');
    echo $result->asText(); // "Echo: Hello AI"

Vector Results
~~~~~~~~~~~~~~

::

    use Symfony\AI\Platform\Result\VectorResult;

    $platform = new InMemoryPlatform(
        fn() => new VectorResult(new Vector([0.1, 0.2, 0.3, 0.4]))
    );

    $result = $platform->invoke('gpt-4o-mini', 'vectorize this text');
    $vectors = $result->asVectors(); // Returns Vector object with [0.1, 0.2, 0.3, 0.4]

Binary Results
~~~~~~~~~~~~~~

::

    use Symfony\AI\Platform\Result\BinaryResult;

    $platform = new InMemoryPlatform(
        fn() => new BinaryResult('fake-pdf-content', 'application/pdf')
    );

    $result = $platform->invoke('gpt-4o-mini', 'generate PDF document');
    $binary = $result->asBinary(); // Returns Binary object with content and MIME type

Raw Results
~~~~~~~~~~~

The platform automatically uses the ``getRawResult()`` from any ``ResultInterface`` returned by closures. For string results, it creates an ``InMemoryRawResult`` to simulate real API response metadata.

This allows fast and isolated testing of AI-powered features without relying on live providers or HTTP requests.

.. note::

    This requires `cURL` and the `ext-curl` extension to be installed.

Code Examples
~~~~~~~~~~~~~

* `Parallel GPT Calls`_
* `Parallel Embeddings Calls`_
* `Cerebras Chat`_
* `Cerebras Streaming`_

.. note::

    Please be aware that some embedding models also support batch processing out of the box.

.. _`OpenAI's GPT`: https://platform.openai.com/docs/models/overview
.. _`OpenAI`: https://platform.openai.com/docs/overview
.. _`Azure`: https://learn.microsoft.com/azure/ai-services/openai/concepts/models
.. _`Anthropic's Claude`: https://www.anthropic.com/claude
.. _`Anthropic`: https://www.anthropic.com/
.. _`AWS Bedrock`: https://aws.amazon.com/bedrock/
.. _`Cartesia`: https://cartesia.ai/sonic
.. _`Meta's Llama`: https://www.llama.com/
.. _`Ollama`: https://ollama.com/
.. _`Replicate`: https://replicate.com/
.. _`Gemini`: https://gemini.google.com/
.. _`Vertex AI`: https://cloud.google.com/vertex-ai/generative-ai/docs
.. _`Google`: https://ai.google.dev/
.. _`OpenRouter`: https://www.openrouter.ai/
.. _`DeepSeek's R1`: https://www.deepseek.com/
.. _`Amazon's Nova`: https://nova.amazon.com
.. _`Mistral's Mistral`: https://www.mistral.ai/
.. _`Albert API`: https://github.com/etalab-ia/albert-api
.. _`Albert`: https://alliance.numerique.gouv.fr/produit/produits-interminist%C3%A9rielles/albert-api/
.. _`Mistral`: https://www.mistral.ai/
.. _`Gemini Text Embeddings`: https://ai.google.dev/gemini-api/docs/embeddings
.. _`Vertex AI Gen AI`: https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/inference
.. _`Vertex AI Text Embeddings`: https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/text-embeddings-api
.. _`OpenAI's Text Embeddings`: https://platform.openai.com/docs/guides/embeddings/embedding-models
.. _`Voyage's Embeddings`: https://docs.voyageai.com/docs/embeddings
.. _`Voyage`: https://www.voyageai.com/
.. _`Mistral Embed`: https://www.mistral.ai/
.. _`OpenAI's Dall·E`: https://platform.openai.com/docs/guides/image-generation
.. _`OpenAI's Whisper`: https://platform.openai.com/docs/guides/speech-to-text
.. _`HuggingFace`: https://huggingface.co/
.. _`Mercure`: https://mercure.rocks/
.. _`Streaming Claude`: https://github.com/symfony/ai/blob/main/examples/anthropic/stream.php
.. _`Streaming GPT`: https://github.com/symfony/ai/blob/main/examples/openai/stream.php
.. _`Streaming Mistral`: https://github.com/symfony/ai/blob/main/examples/mistral/stream.php
.. _`Binary Image Input with GPT`: https://github.com/symfony/ai/blob/main/examples/openai/image-input-binary.php
.. _`Image URL Input with GPT`: https://github.com/symfony/ai/blob/main/examples/openai/image-input-url.php
.. _`Audio Input with GPT`: https://github.com/symfony/ai/blob/main/examples/openai/audio-input.php
.. _`Embeddings with OpenAI`: https://github.com/symfony/ai/blob/main/examples/openai/embeddings.php
.. _`Embeddings with Voyage`: https://github.com/symfony/ai/blob/main/examples/voyage/embeddings.php
.. _`Embeddings with Mistral`: https://github.com/symfony/ai/blob/main/examples/mistral/embeddings.php
.. _`Structured Output with PHP class`: https://github.com/symfony/ai/blob/main/examples/openai/structured-output-math.php
.. _`Structured Output with array`: https://github.com/symfony/ai/blob/main/examples/openai/structured-output-clock.php
.. _`Parallel GPT Calls`: https://github.com/symfony/ai/blob/main/examples/misc/parallel-chat-gpt.php
.. _`Parallel Embeddings Calls`: https://github.com/symfony/ai/blob/main/examples/misc/parallel-embeddings.php
.. _`LM Studio`: https://lmstudio.ai/
.. _`LM Studio Catalog`: https://lmstudio.ai/models
.. _`Cerebras Chat`: https://github.com/symfony/ai/blob/main/examples/cerebras/chat.php
.. _`Cerebras Streaming`: https://github.com/symfony/ai/blob/main/examples/cerebras/stream.php
