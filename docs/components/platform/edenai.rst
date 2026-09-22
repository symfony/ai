Eden AI
=======

Eden AI is an AI gateway exposing hundreds of models from many providers behind a
single API, addressed with ``provider/model`` identifiers. The bridge covers both halves of
the v3 API: the OpenAI-compatible endpoints (``/v3/chat/completions`` and ``/v3/embeddings``,
reusing the Generic bridge, including streaming and tool calling) and the expert models of
the ``/v3/universal-ai`` endpoints.

For comprehensive information about Eden AI, see the `Eden AI API reference`_.

Installation
------------

To use Eden AI with Symfony AI Platform, install the bridge:

.. code-block:: terminal

    $ composer require symfony/ai-eden-ai-platform

Setup
-----

Authentication
~~~~~~~~~~~~~~

Eden AI requires an API key, which you can create from the `Eden AI dashboard`_. Configure it in your
environment file:

.. code-block:: bash

    EDENAI_API_KEY=your-eden-ai-api-key

Usage
-----

Chat and Embeddings
~~~~~~~~~~~~~~~~~~~

The bridge supports OpenAI-compatible chat and embeddings endpoints, so streaming and tool
calling work as with any Generic-bridge platform::

    use Symfony\AI\Platform\Bridge\EdenAi\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($apiKey);

    $messages = new MessageBag(Message::ofUser('What is the Symfony framework?'));
    echo $platform->invoke('openai/gpt-4o-mini', $messages)->asText();

    $vectors = $platform->invoke('openai/text-embedding-3-small', 'Some text')->asVectors();

OCR and Document Parsing
~~~~~~~~~~~~~~~~~~~~~~~~

Eden AI exposes OCR and document parsing (invoices, resumes, identity documents) from multiple
providers through its ``/v3/universal-ai`` endpoint, using ``ocr/{subfeature}/{provider}`` model
names. Models are invoked with a
:class:`Symfony\\AI\\Platform\\Message\\Content\\DocumentUrl` or
:class:`Symfony\\AI\\Platform\\Message\\Content\\ImageUrl`, or with a plain string holding a
direct file URL or a file ID from Eden AI's upload API. Input parameters like ``language`` or
``document_type`` are passed as options, or inline in the model name
(``ocr/ocr/google?language=en``)::

    use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser\Result\DocumentParsingResult;
    use Symfony\AI\Platform\Bridge\EdenAi\Factory;
    use Symfony\AI\Platform\Bridge\EdenAi\Ocr\Result\OcrResult;
    use Symfony\AI\Platform\Message\Content\DocumentUrl;
    use Symfony\AI\Platform\Message\Content\ImageUrl;

    $platform = Factory::createPlatform($apiKey);

    // OCR: extract raw text and bounding boxes
    $result = $platform->invoke('ocr/ocr/google', new ImageUrl('https://example.com/scan.jpg'), [
        'language' => 'en',
    ]);

    $ocr = $result->asObject();
    \assert($ocr instanceof OcrResult);

    echo $ocr->getText();

    // Document parsing: extract structured data from an invoice
    $result = $platform->invoke('ocr/financial_parser/affinda', new DocumentUrl('https://example.com/invoice.pdf'), [
        'language' => 'en',
        'document_type' => 'invoice',
    ]);

    $parsing = $result->asObject();
    \assert($parsing instanceof DocumentParsingResult);

    $extractedData = $parsing->getExtractedData();

.. note::

    Not every provider reads an uploaded file. ``ocr/financial_parser/openai`` answers a
    successful but empty result when its input is a file ID, while it parses the very same
    document handed over as a URL. ``ocr/financial_parser/affinda`` reads both. Pass a
    :class:`Symfony\\AI\\Platform\\Message\\Content\\DocumentUrl` to the providers behaving that way.

Expert Models
~~~~~~~~~~~~~

Beyond OCR and document parsing, the Eden AI bridge exposes the other expert models of the
``/v3/universal-ai`` endpoints: text-to-speech, speech-to-text, image analysis (object
detection, explicit content, logo detection, face detection, AI detection and deepfake
detection) and image generation. Binary content
(:class:`Symfony\\AI\\Platform\\Message\\Content\\Audio`,
:class:`Symfony\\AI\\Platform\\Message\\Content\\Document` or
:class:`Symfony\\AI\\Platform\\Message\\Content\\Image`) is transparently uploaded through
Eden AI's ``/v3/upload`` endpoint before the request::

    use Symfony\AI\Platform\Bridge\EdenAi\Factory;
    use Symfony\AI\Platform\Message\Content\Audio;
    use Symfony\AI\Platform\Message\Content\ImageUrl;

    $platform = Factory::createPlatform($apiKey);

    // Text-to-speech: returns the synthesized audio as binary data
    $result = $platform->invoke('audio/tts/amazon/neural', 'Welcome to Symfony AI!');
    $result->asFile('welcome.mp3');

    // Speech-to-text: diarization is exposed as result metadata
    $result = $platform->invoke('audio/speech_to_text_async/openai', 'https://example.com/audio.mp3');
    echo $result->asText();

    // Speech-to-text from a local file, uploaded automatically
    $result = $platform->invoke('audio/speech_to_text_async/deepgram', Audio::fromFile('./audio.mp3'));

    // Object detection
    $analysis = $platform->invoke('image/object_detection/google', new ImageUrl('https://example.com/photo.jpg'))->asObject();
    foreach ($analysis->getItems() as $item) {
        echo $item['label'];
    }

    // Image generation: every generated image is returned, so requesting several yields a
    // MultiPartResult instead of a single BinaryResult
    $result = $platform->invoke('image/generation/stabilityai', 'A red apple on a white table');
    $result->asFile('apple.png');

Asynchronous Transcription
~~~~~~~~~~~~~~~~~~~~~~~~~~

Speech-to-text runs on ``/v3/universal-ai/async``. Providers that are done by the time Eden AI
answers put the transcription straight into the response, and the invocation is over. The others
report a job that is still running, and the invocation returns a
:class:`Symfony\\AI\\Platform\\Result\\JobResult` instead - a handle that can be waited for right
away, or stored and picked up by a worker later, since it holds no connection::

    use Symfony\AI\Platform\Bridge\EdenAi\Factory;
    use Symfony\AI\Platform\Job\JobRunner;
    use Symfony\AI\Platform\Result\JobResult;

    $result = $platform->invoke('audio/speech_to_text_async/deepgram', 'https://example.com/audio.mp3');

    if ($result->getResult() instanceof JobResult) {
        $jobClient = Factory::createJobClient($apiKey);

        // The handle states how long transcription may run through the gateway, so the caller
        // does not have to know; pass maxDuration to overrule it.
        $result = (new JobRunner())->wait($jobClient, $result->asJob(), maxDuration: 60);
    }

    echo $result->asText();

The bridge never blocks inside ``invoke()``: how long to wait for a job, and whether to wait at
all, is the caller's decision. Pass a ``webhook_receiver`` option to be notified out of band
instead, and resolve the handle when the webhook fires.

In a Symfony application the job client is available as a service, autowirable by name::

    public function __construct(
        #[Autowire(service: 'ai.platform.job_client.edenai')]
        private JobClientInterface $edenaiJobClient,
        private JobRunner $jobRunner,
    ) {
    }

Eden AI fronts more than a thousand models, and
:class:`Symfony\\AI\\Platform\\Bridge\\EdenAi\\ModelCatalog` only curates a subset of them.
Register any other one through its ``$additionalModels`` argument, or hand the factory a
:class:`Symfony\\AI\\Platform\\Bridge\\EdenAi\\ModelApiCatalog`, which discovers everything
Eden AI currently serves from its public ``/v3/models``, ``/v3/embeddings/models`` and
``/v3/info`` endpoints::

    use Symfony\AI\Platform\Bridge\EdenAi\Factory;
    use Symfony\AI\Platform\Bridge\EdenAi\ModelApiCatalog;

    $platform = Factory::createPlatform($apiKey, $httpClient, new ModelApiCatalog($httpClient));

    // No catalog entry needed for this one
    $result = $platform->invoke('audio/tts/elevenlabs/eleven_multilingual_v2', 'Welcome!');

Expert subfeatures the bridge has no result converter for stay hidden from that catalog, so
an unsupported model fails at lookup time rather than during conversion.

Code Examples
-------------

* `OCR with Eden AI`_
* `Invoice parsing with Eden AI`_
* `Resume parsing with Eden AI`_
* `TTS with Eden AI`_
* `STT with Eden AI`_
* `STT with Eden AI (file upload)`_
* `Object detection with Eden AI`_
* `Logo detection with Eden AI`_
* `Image generation with Eden AI`_

.. _`Eden AI API reference`: https://docs.edenai.co/
.. _`Eden AI dashboard`: https://app.edenai.run/
.. _`OCR with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/ocr.php
.. _`Invoice parsing with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/financial-parser.php
.. _`Resume parsing with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/resume-parser.php
.. _`TTS with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/tts.php
.. _`STT with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/speech-to-text.php
.. _`STT with Eden AI (file upload)`: https://github.com/symfony/ai/blob/main/examples/edenai/speech-to-text-upload.php
.. _`Object detection with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/object-detection.php
.. _`Logo detection with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/logo-detection.php
.. _`Image generation with Eden AI`: https://github.com/symfony/ai/blob/main/examples/edenai/image-generation.php
