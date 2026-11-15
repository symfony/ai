Decision Models
===============

A decision model doesn't generate text. It answers typed questions about a piece of content, called the
*state*, with probabilities your code can act on. Use it for classification, routing, scoring and
guardrails, when your application needs a decision and not a sentence.

The classes to ask and to read decisions are part of the Platform component, in the
``Symfony\AI\Platform\Decision`` namespace. Every platform with decision models uses them. Your code stays the same when you switch from one
provider to another.

Supported Platforms
-------------------

================  ======================================  ================================================
Platform          Package                                 Models
================  ======================================  ================================================
`Gemini`_         ``symfony/ai-gemini-platform``          ``gemini-decision-1``
`OpenAI`_         ``symfony/ai-open-ai-platform``         ``gpt-6-luna``
`TypeSafe`_       ``symfony/ai-type-safe-platform``       ``jev-latest``, see :doc:`typesafe`
`Ollama`_         ``symfony/ai-ollama-platform``          models with the ``decision`` capability
`OpenRouter`_     ``symfony/ai-open-router-platform``     models of the ``DecisionModelCatalog``
================  ======================================  ================================================

OpenRouter serves its decision models through its own catalog, the
:class:`Symfony\\AI\\Platform\\Bridge\\OpenRouter\\Decision\\DecisionModelCatalog`.

Asking Questions
----------------

Pass the state as the input of the platform, and the questions in the ``questions`` option. Every question
has an identifier of your choice. The model reads the state once and answers every question against it, so
one invocation with several questions is faster and cheaper than one invocation per question::

    use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
    use Symfony\AI\Platform\Decision\Question\NoulQuestion;
    use Symfony\AI\Platform\Decision\Question\ScoreQuestion;

    $result = $platform->invoke('gemini-decision-1', $ticket, ['questions' => [
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
        'is_urgent' => new NoulQuestion('Does the message convey urgency?', 'Urgent or time-sensitive', 'No time pressure'),
    ]]);

The state is a string, a :class:`Symfony\\AI\\Platform\\Message\\Content\\Text` or structured data as an
array, like a record of your application. There are three types of questions:

* :class:`Symfony\\AI\\Platform\\Decision\\Question\\NoulQuestion` asks a yes/no question. The
  optional second and third arguments describe what a yes and a no mean;
* :class:`Symfony\\AI\\Platform\\Decision\\Question\\ChoiceQuestion` picks one of at least two options.
  Every option maps to its description, or to ``null`` when the name of the option is clear on its own;
* :class:`Symfony\\AI\\Platform\\Decision\\Question\\ScoreQuestion` rates the state along at least two
  levels, ordered from the lowest to the highest.

.. note::

    OpenAI has no criteria for yes/no questions. Its bridge adds the descriptions of a yes and a no to the
    instructions of the question.

Images
~~~~~~

Pass the text and the images of the state in a
:class:`Symfony\\AI\\Platform\\Message\\Content\\Collection`. All questions share them::

    use Symfony\AI\Platform\Message\Content\Collection;
    use Symfony\AI\Platform\Message\Content\Image;

    $result = $platform->invoke('gemini-decision-1', new Collection(
        'A photograph to classify.',
        Image::fromFile('/path/to/photo.jpg'),
    ), ['questions' => [
        'outdoors' => new NoulQuestion('Was this photograph taken outdoors?'),
    ]]);

Gemini, OpenAI and the Clef models of Ollama accept images. Other models throw an
:class:`Symfony\\AI\\Platform\\Exception\\InvalidArgumentException` before the request is sent.

Gemini and OpenAI keep the order of the texts and images. The other platforms receive the texts as one state,
separated by a blank line, and the images after it.

Reading the Answers
-------------------

The result holds an :class:`Symfony\\AI\\Platform\\Decision\\Answer\\Answers` object, with every answer
under the identifier of its question. The ``getNoul()``, ``getChoice()`` and ``getScore()`` methods return the
answer with its type, and throw an exception when the answer has another type::

    use Symfony\AI\Platform\Decision\Answer\Answers;

    $answers = $result->asObject(Answers::class);

    $urgent = $answers->getNoul('is_urgent');
    $urgent->getProbability(); // 0.98, the probability that the answer is yes
    $urgent->isTrue();         // true, the probability reaches the default threshold of 0.5
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

The confidence tells how certain the model is about a choice or a score. Use it to decide whether to act on an
answer, or to hand the case over to another model or to a human::

    if ($department->getConfidence() < 0.7) {
        // route the ticket to a human for review
    }

.. note::

    Decisions don't take messages, call tools or stream their answers, so an agent can't run them. Use them
    next to an agent, for example to classify the input of a user before you choose the agent to hand it to.

The ``questions`` option needs a model with the ``Capability::DECISION`` capability. Other models throw a
:class:`Symfony\\AI\\Platform\\Exception\\MissingModelSupportException`. ``gpt-6-luna`` answers on the
Responses API as long as you don't pass questions, so one OpenAI platform serves both uses.

Switching Providers in the Bundle
---------------------------------

With the AI Bundle, the platform and the model of a decision are configuration. Inject both into your
service:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            gemini:
                api_key: '%env(GEMINI_API_KEY)%'

    # config/services.yaml
    services:
        App\Support\TicketTriage:
            arguments:
                $platform: '@ai.platform.gemini'
                $model: 'gemini-decision-1'

To use OpenAI, configure its platform and change the two arguments:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'

    # config/services.yaml
    services:
        App\Support\TicketTriage:
            arguments:
                $platform: '@ai.platform.openai'
                $model: 'gpt-6-luna'

To use OpenRouter, enable the API catalog of its platform. It lists the decision models next to the chat
models:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openrouter:
                api_key: '%env(OPENROUTER_API_KEY)%'
                model_catalog:
                    api: true

Examples
--------

The ``decision.php`` example triages a support ticket in the ``examples/gemini/``, ``examples/openai/``,
``examples/typesafe/``, ``examples/ollama/`` and ``examples/openrouter/`` directories. Only the platform and the
model differ between them. The ``decision-image.php`` examples of Gemini, OpenAI and Ollama classify a
photograph.

.. _`Gemini`: https://aistudio.google.com/docs/decisions-api
.. _`OpenAI`: https://developers.openai.com/api/docs/guides/decisions
.. _`TypeSafe`: https://typesafe.ai/
.. _`Ollama`: https://docs.ollama.com/capabilities/decision
.. _`OpenRouter`: https://openrouter.ai/docs/api/api-reference/alphadecisions/submit-a-decisions-request
