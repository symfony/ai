TypeSafe
========

TypeSafe's Jev is a decision model. It answers typed questions about a piece of content with calibrated
probabilities, see :doc:`decision`.

For more information about TypeSafe, see the `TypeSafe API reference`_.

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

Pass the questions to Jev in the ``questions`` option and read its answers like the ones of any other
decision model::

    use Symfony\AI\Platform\Bridge\TypeSafe\Factory;
    use Symfony\AI\Platform\Decision\Question\NoulQuestion;

    $platform = Factory::createPlatform($_ENV['TYPESAFE_API_KEY'], $httpClient);

    $result = $platform->invoke('jev-latest', $ticket, ['questions' => [
        'is_urgent' => new NoulQuestion('Does the message convey urgency?'),
    ]]);

Jev doesn't accept images.

Models
------

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

* ``decision.php`` triages a support ticket with the three types of questions

.. _TypeSafe API reference: https://docs.typesafe.ai/api
.. _TypeSafe console: https://console.typesafe.ai/keys
