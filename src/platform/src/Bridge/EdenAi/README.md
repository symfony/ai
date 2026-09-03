Eden AI Platform
================

Eden AI platform bridge for Symfony AI.

Supports chat completions and embeddings through Eden AI's OpenAI-compatible v3 API, plus
the universal-ai expert models: OCR, document parsing (invoices, resumes, identity
documents), text-to-speech, speech-to-text, image analysis (object detection, explicit
content, logo detection, face detection, AI detection, deepfake detection) and image
generation. Local binary files are transparently uploaded through the upload API.

Model catalogs
--------------

Eden AI fronts more than a thousand models, so the bridge ships two catalogs:

 * `ModelCatalog` is a curated static subset. Register anything else through its
   `$additionalModels` argument, mapping the model id to the class handling that
   feature (`Ocr`, `DocumentParser`, `Tts`, `SpeechToText`, `ImageAnalysis`,
   `ImageGeneration`, or the generic `CompletionsModel`/`EmbeddingsModel`):

   ```php
   use Symfony\AI\Platform\Bridge\EdenAi\ModelCatalog;
   use Symfony\AI\Platform\Bridge\EdenAi\Tts;
   use Symfony\AI\Platform\Capability;

   $catalog = new ModelCatalog([
       'audio/tts/elevenlabs/eleven_multilingual_v2' => [
           'class' => Tts::class,
           'capabilities' => [Capability::TEXT_TO_SPEECH],
       ],
   ]);
   ```

 * `ModelApiCatalog` discovers every model Eden AI currently serves, from the public
   `/v3/models`, `/v3/embeddings/models` and `/v3/info` endpoints, so no model id has
   to be registered by hand:

   ```php
   use Symfony\AI\Platform\Bridge\EdenAi\Factory;
   use Symfony\AI\Platform\Bridge\EdenAi\ModelApiCatalog;

   $platform = Factory::createPlatform($apiKey, $httpClient, new ModelApiCatalog($httpClient));
   ```

   Expert subfeatures the bridge has no result converter for stay hidden, so an
   unsupported one fails at lookup rather than at conversion time.

Eden AI Documentation
---------------------

 * [Chat completions](https://www.edenai.co/docs/api-reference/chat/chat-completions)
 * [Embeddings](https://www.edenai.co/docs/api-reference/embeddings/create-embeddings)
 * [OCR](https://www.edenai.co/docs/v3/expert-models/features/ocr/ocr)
 * [Financial parser](https://www.edenai.co/docs/v3/expert-models/features/ocr/financial-parser)
 * [Resume parser](https://www.edenai.co/docs/v3/expert-models/features/ocr/resume-parser)
 * [Identity parser](https://www.edenai.co/docs/v3/expert-models/features/ocr/identity-parser)
 * [Text-to-speech](https://www.edenai.co/docs/v3/expert-models/features/audio/tts)
 * [Speech-to-text](https://www.edenai.co/docs/v3/expert-models/features/audio/speech-to-text-async)
 * [Object detection](https://www.edenai.co/docs/v3/expert-models/features/image/object-detection)
 * [Explicit content detection](https://www.edenai.co/docs/v3/expert-models/features/image/explicit-content)
 * [Image generation](https://www.edenai.co/docs/v3/expert-models/features/image/generation)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
