TypeSafe
========

TypeSafe's Jev is a System One model: instead of generating text, it answers typed questions about a piece of
content - the *state* - with calibrated probabilities your code can act on directly. This makes it a good fit for
classification, routing, scoring and guardrails, where an application needs a decision rather than a sentence.

For comprehensive information about TypeSafe, see the `TypeSafe API reference`_.

Setup
-----

Installation
~~~~~~~~~~~~

.. code-block:: terminal

    $ composer require symfony/ai-type-safe-platform

Authentication
~~~~~~~~~~~~~~

TypeSafe requires an API key, which you can set up in the `TypeSafe console`_.

Usage
-----

An invocation takes an :class:`Symfony\\AI\\Platform\\Bridge\\TypeSafe\\Evaluation`, made of a state and the questions
to answer about it, keyed by an identifier of your choice. Jev reads the state once and answers every question
against it, so asking several questions at once is both faster and cheaper than one invocation per question::

    use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
    use Symfony\AI\Platform\Bridge\TypeSafe\Factory;
    use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
    use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
    use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;

    $platform = Factory::createPlatform($_ENV['TYPESAFE_API_KEY'], $httpClient);

    $result = $platform->invoke('jev-latest', new Evaluation($ticket, [
        'department' => new ChoiceQuestion('Which team should handle this?', [
            'billing' => 'Payment or subscription issues',
            'technical' => 'Bugs or integration problems',
            'sales' => 'Pricing or account questions',
        ]),
        'frustration' => new ScoreQuestion('How frustrated does the customer appear?', [
            'Calm, just stating facts',
            'Frustrated but civil',
            'Very angry, strong language',
        ]),
        'is_urgent' => new NoulQuestion('Does the message convey urgency?'),
    ]));

    $answers = $result->asObject();

The state is either plain text or structured data, like a list of chat messages or a record of your application.

Questions
~~~~~~~~~

There are three types of questions, each one coming with its own type of answer:

* :class:`Symfony\\AI\\Platform\\Bridge\\TypeSafe\\Question\\NoulQuestion` asks a yes/no question. An optional
  description of what a yes and a no mean can be given as second and third argument;
* :class:`Symfony\\AI\\Platform\\Bridge\\TypeSafe\\Question\\ChoiceQuestion` picks one option out of a set. Every
  option maps to its description, or to ``null`` when the name of the option speaks for itself;
* :class:`Symfony\\AI\\Platform\\Bridge\\TypeSafe\\Question\\ScoreQuestion` rates the state along levels, ordered
  from the lowest to the highest.

Answers
~~~~~~~

The result holds an :class:`Symfony\\AI\\Platform\\Bridge\\TypeSafe\\Answer\\Answers` object, giving access to every
answer by the identifier of its question. The ``getNoul()``, ``getChoice()`` and ``getScore()`` methods return the
answer with its type, and throw an exception when the answer is of another type::

    $urgent = $answers->getNoul('is_urgent');
    $urgent->getProbability(); // 0.98, the probability that the answer is yes
    $urgent->isTrue();         // true, as the probability reaches the default threshold of 0.5
    $urgent->isTrue(0.99);     // false

    $department = $answers->getChoice('department');
    $department->getChoice();        // 'billing'
    $department->getProbabilities(); // ['billing' => 0.65, 'technical' => 0.35, 'sales' => 0.0]
    $department->getConfidence();    // 0.48

    $frustration = $answers->getScore('frustration');
    $frustration->getScore();         // 1.0, the probability-weighted level, which can land between two levels
    $frustration->getLegend();        // [0 => 'Calm, just stating facts', 1 => 'Frustrated but civil', ...]
    $frustration->getProbabilities(); // [0 => 0.0, 1 => 1.0, 2 => 0.0]
    $frustration->getConfidence();    // 1.0

The confidence tells how certain the model is about a choice or a score, which is what lets your code decide
whether to act on an answer, or to fall back to another model or to a human::

    if ($department->getConfidence() < 0.7) {
        // route the ticket to a human for review
    }

.. note::

    Jev does not take messages, call tools nor stream its answers, so it cannot be the model of an agent. Use it
    next to one instead, for example to classify the input of a user before choosing the agent to hand it to.

Models
~~~~~~

The ``jev-latest`` and ``jev-preview`` aliases follow the releases of Jev, so their answers can change over time. Use
a versioned model, like ``jev-1.13.0``, once confidence thresholds are tuned against it. The model that answered is
part of the token usage::

    $result->getMetadata()->get('token_usage')->getModel(); // 'jev-1.13.0'

Symfony Bundle Configuration
----------------------------

When using the AI Bundle, configure the API key under the ``typesafe`` platform section:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            typesafe:
                api_key: '%env(TYPESAFE_API_KEY)%'

The platform is then available as the ``ai.platform.typesafe`` service.

Examples
--------

See the ``examples/typesafe/`` directory for complete working examples:

* ``evaluate.php`` - Triaging a support ticket with the three types of questions

.. _TypeSafe API reference: https://docs.typesafe.ai/api
.. _TypeSafe console: https://console.typesafe.ai/keys
