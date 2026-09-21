Jev (TypeSafe)
==============

Jev is TypeSafe's System One model. It evaluates typed questions against a
state and returns structured answers with probabilities. It is not a
chat-completion model.

For the HTTP API, see the `TypeSafe API reference`_.

Setup
-----

Authentication
~~~~~~~~~~~~~~

Requests use a Bearer token from the `TypeSafe console`_. Official clients read
the key from the ``TYPESAFE_API_KEY`` environment variable.

Usage
-----

The invoke input is the ``state`` (a string or a JSON object/array). Pass the
typed questions in the ``questions`` option::

    use Symfony\AI\Platform\Bridge\Jev\Factory;
    use Symfony\AI\Platform\Bridge\Jev\Output\EvaluationResult;

    $platform = Factory::createPlatform($_ENV['TYPESAFE_API_KEY'], $httpClient);

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

    assert($result instanceof EvaluationResult);
    $result->getChoice('topic');
    $result->getProbabilities('topic');
    $result->getNoul('urgent');

Question types are ``choice``, ``noul``, and ``score``. Catalog models are
``jev-latest``, ``jev-preview``, and ``jev-1.13.0``. The only declared
capability is ``INPUT_TEXT``.

Bundle configuration::

    # config/packages/ai.yaml
    ai:
        platform:
            jev:
                api_key: '%env(TYPESAFE_API_KEY)%'

Examples
--------

See ``examples/jev/evaluate.php``.

.. _TypeSafe API reference: https://docs.typesafe.ai/api
.. _TypeSafe console: https://console.typesafe.ai/settings/keys
