Ollama Platform
===============

Ollama platform bridge for Symfony AI.

Decision models
---------------

Models declaring the `decision` capability (Ollama >= 0.35.0) are served through `POST /v1/systemone`.
This requires `symfony/ai-decision-platform`.
They are queried with the `Evaluation` of the `Decision` bridge and answer with its `Answers`.

Images (Clef models) are not supported yet.

Ollama Documentation
--------------------

 * [API reference (chat, embed, NDJSON streaming)](https://github.com/ollama/ollama/blob/main/docs/api.md)
 * [Modelfile / capabilities](https://github.com/ollama/ollama/blob/main/docs/modelfile.mdx)
 * [Vision (image input)](https://docs.ollama.com/capabilities/vision)
 * [Decision models](https://docs.ollama.com/capabilities/decision)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
