OpenRouter Platform
===================

OpenRouter platform bridge for Symfony AI.

Decision models
---------------

Decision models are served through the experimental `POST /api/alpha/decisions` endpoint.
This requires `symfony/ai-decision-platform`. They are queried with the `Evaluation` of the
`Decision` bridge and answer with its `Answers`. Use the `DecisionModelCatalog` or the
`ModelApiCatalog`, which lists them via `GET /v1/models?output_modalities=decisions`.

```php
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\OpenRouter\Decision\DecisionModelCatalog;
use Symfony\AI\Platform\Bridge\OpenRouter\Factory;

$platform = Factory::createPlatform($apiKey, modelCatalog: new DecisionModelCatalog());

$answers = $platform->invoke('typesafe/jev-1.13', new Evaluation($ticket, [
    'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question or feature request'),
]))->asObject();
```

OpenRouter requires both criteria of a `NoulQuestion`. The `provider`, `session_id`, `trace` and
`user` options are forwarded to the request. Image inputs and `box` questions are not supported yet.

OpenRouter Documentation
------------------------

 * [Chat completion](https://openrouter.ai/docs/api/api-reference/chat/send-chat-completion-request)
 * [Streaming](https://openrouter.ai/docs/api/reference/streaming)
 * [Get models](https://openrouter.ai/docs/api/api-reference/models/get-models)
 * [Rerank](https://openrouter.ai/docs/api/api-reference/rerank/create-rerank)
 * [Decisions](https://openrouter.ai/docs/api/api-reference/alphadecisions/submit-a-decisions-request)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
