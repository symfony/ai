Eden AI Platform
================

Eden AI platform bridge for Symfony AI.

Supports chat completions and embeddings through Eden AI's OpenAI-compatible v3 API, plus
the universal-ai expert models: OCR, document parsing (invoices, resumes, identity
documents), text-to-speech, speech-to-text, image analysis (object detection, explicit
content, logo detection, face detection, AI detection, deepfake detection) and image
generation. Local binary files are transparently uploaded through the upload API, and
speech-to-text jobs that are still running are handed out as a `JobResult` instead of
being waited for inside `invoke()`.

The bridge ships two model catalogs: `ModelCatalog` curates a static subset and takes extra
entries through its `$additionalModels` argument, while `ModelApiCatalog` discovers every
model the gateway currently serves from its public endpoints.

See the [full documentation](https://symfony.com/doc/current/ai/components/platform/edenai.html)
for setup and usage details.

Eden AI Documentation
---------------------

 * [API reference](https://www.edenai.co/docs/api-reference/universal-ai/universal-ai)
 * [OpenAPI schema](https://api.edenai.run/v3/docs/openapi.json)
 * [Chat completions](https://www.edenai.co/docs/api-reference/chat/chat-completions)
 * [Embeddings](https://www.edenai.co/docs/api-reference/embeddings/create-embeddings)
 * [Expert models](https://www.edenai.co/docs/v3/quickstart/first-expert-model-call)
 * [Asynchronous jobs](https://www.edenai.co/docs/api-reference/universal-ai/get-async-job)
 * [Webhooks](https://www.edenai.co/docs/v3/expert-models/webhooks)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
