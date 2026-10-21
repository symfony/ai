Cheaper Inference
=================

Cheaper Inference is an OpenAI-compatible LLM gateway. One API key gives access to models
from several labs, addressed with bare model names such as ``gpt-5.4-mini``. The bridge uses
the ``/v1/chat/completions`` endpoint through the Generic bridge, including streaming and
tool calling.

For comprehensive information about Cheaper Inference, see the `Cheaper Inference documentation`_.

Installation
------------

To use Cheaper Inference with Symfony AI Platform, install the bridge:

.. code-block:: terminal

    $ composer require symfony/ai-cheaper-inference-platform

Setup
-----

Authentication
~~~~~~~~~~~~~~

Cheaper Inference requires an API key, which you can create on the `Cheaper Inference website`_.
Configure it in your environment file:

.. code-block:: bash

    CHEAPER_INFERENCE_API_KEY=your-api-key

Usage
-----

::

    use Symfony\AI\Platform\Bridge\CheaperInference\Factory;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $platform = Factory::createPlatform($apiKey);

    $messages = new MessageBag(Message::ofUser('What is the Symfony framework?'));
    echo $platform->invoke('gpt-5.4-mini', $messages)->asText();

The bridge ships a curated :class:`Symfony\\AI\\Platform\\Bridge\\CheaperInference\\ModelCatalog`.
Pass extra models through its ``$additionalModels`` argument, or see :doc:`model-catalogs`.

Bundle Configuration
--------------------

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            cheaperinference:
                api_key: '%env(CHEAPER_INFERENCE_API_KEY)%'

Code Examples
-------------

* `Chat with Cheaper Inference`_
* `Streaming with Cheaper Inference`_
* `Tool calling with Cheaper Inference`_

.. _`Cheaper Inference documentation`: https://cheaperinference.com/docs
.. _`Cheaper Inference website`: https://cheaperinference.com/signup
.. _`Chat with Cheaper Inference`: https://github.com/symfony/ai/blob/main/examples/cheaperinference/chat.php
.. _`Streaming with Cheaper Inference`: https://github.com/symfony/ai/blob/main/examples/cheaperinference/stream.php
.. _`Tool calling with Cheaper Inference`: https://github.com/symfony/ai/blob/main/examples/cheaperinference/toolcall.php
