Jev Platform
============

Jev (TypeSafe System One) platform bridge for Symfony AI.

Jev evaluates typed questions against a state and returns structured answers
with probabilities. It is not a chat-completion model.

Installation
------------

```bash
composer require symfony/ai-jev-platform
```

Configuration
-------------

Create an API key in the TypeSafe console and expose it as `TYPESAFE_API_KEY`.
Requests use `Authorization: Bearer`.

Usage
-----

```php
use Symfony\AI\Platform\Bridge\Jev\Factory;
use Symfony\AI\Platform\Bridge\Jev\Output\EvaluationResult;

$platform = Factory::createPlatform($_ENV['TYPESAFE_API_KEY']);

$result = $platform->invoke('jev-latest', 'Customer: I was charged twice and I am furious.', [
    'questions' => [
        'topic' => [
            'type' => 'choice',
            'instructions' => 'What is the issue about?',
            'criteria' => [
                'billing' => 'money problems',
                'bug' => 'broken product',
            ],
        ],
        'urgent' => [
            'type' => 'noul',
            'instructions' => 'Escalate to a human now?',
        ],
    ],
])->asObject();

\assert($result instanceof EvaluationResult);
$result->getChoice('topic');
$result->getProbabilities('topic');
$result->getNoul('urgent');
```

The `questions` option is required. `state` (the invoke input) may be a string
or a JSON object/array. See the TypeSafe API reference for question types
(`choice`, `noul`, `score`).

Jev Documentation
-----------------

 * [API reference](https://docs.typesafe.ai/api)
 * [System One](https://docs.typesafe.ai/concepts/system-one)
 * [Models](https://docs.typesafe.ai/models)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
