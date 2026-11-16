Hugging Face
============

`Hugging Face Inference Providers`_ give you access to thousands of models for chat,
embeddings, classification, audio and other tasks. The models run on Hugging Face's own
inference service or on third-party providers (e.g. Cerebras, Groq or Together).

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-hugging-face-platform

Setup
-----

The bridge requires a Hugging Face `access token`_. Pass it to the factory::

    use Symfony\AI\Platform\Bridge\HuggingFace\Factory;

    $platform = Factory::createPlatform('hf_your_api_key_here');

Use the model ID from Hugging Face (e.g. ``openai/gpt-oss-120b``) to invoke a model. The
model catalog knows which task the model is served for and returns the matching result::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // chat completion
    $result = $platform->invoke('openai/gpt-oss-120b', new MessageBag(Message::ofUser('Hello!')));
    echo $result->asText();

    // text classification
    $result = $platform->invoke('ProsusAI/finbert', 'I like you. I love you.');
    dump($result->asObject());

Chat Completion
---------------

Chat completion runs on the third-party inference providers. By default, Hugging Face picks
the provider for each request. Pass a provider to the factory to always use the same one::

    use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
    use Symfony\AI\Platform\Bridge\HuggingFace\Provider;

    $platform = Factory::createPlatform('hf_your_api_key_here', Provider::CEREBRAS);

You can also choose the provider for one request, either with the ``provider`` option or
with a suffix on the model ID::

    $result = $platform->invoke('openai/gpt-oss-120b', $messages, [
        'provider' => Provider::GROQ,
    ]);

    // the same request
    $result = $platform->invoke('openai/gpt-oss-120b:groq', $messages);

Use the Hugging Face model ID with every provider. Hugging Face maps it to the name the
provider uses for the model. Chat completion supports streaming with the ``stream`` option::

    $result = $platform->invoke('openai/gpt-oss-120b', $messages, ['stream' => true]);

    foreach ($result->asTextStream() as $delta) {
        echo $delta;
    }

Tool Calling
~~~~~~~~~~~~

Chat completion supports tool calling when the model and the inference provider support
it. The following example uses the Agent component to call a clock tool::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Bridge\Clock\Clock;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
    use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\Component\Clock\NativeClock;

    $platform = Factory::createPlatform('hf_your_api_key_here', Provider::CEREBRAS);
    $toolbox = new Toolbox([new Clock(new NativeClock())]);
    $agent = new Agent($platform, 'openai/gpt-oss-120b', toolbox: $toolbox);

    $result = $agent->call(new MessageBag(Message::ofUser('What is the current date and time?')));

    echo $result->asText();

This example also requires ``symfony/ai-agent`` and ``symfony/ai-clock-tool``.

Which models support tools, and whether they accept the ``tool_choice`` option, depends on
the provider. See the Hugging Face `function calling guide`_ for details.

When you call the platform directly, pass ``Tool`` objects in the ``tools`` option.
If the model calls a tool, the result is a
:class:`Symfony\\AI\\Platform\\Result\\ToolCallResult` with the ID, the name and the
decoded arguments of each call.

Run only tools you know and allow. Then add ``Message::ofAssistant($result)`` and one
``Message::ofToolCall($call, $output)`` per call to the message bag, and invoke the model
again. If the model returns arguments that are not a valid JSON object, the bridge throws a
:class:`Symfony\\AI\\Platform\\Exception\\MalformedToolCallException`.

See the `Hugging Face tool calling example`_ for a complete agent.

Tasks
-----

All other tasks run on the Hugging Face inference service (``hf-inference``), whatever
provider you pass to the factory. The model catalog maps each task to a model class:

* ``Embeddings``: ``feature-extraction`` and ``sentence-similarity``, returns vectors
* ``Reranker``: ``text-ranking``, returns reranking entries
* ``Classification``: ``text-classification`` and ``image-classification``
* ``ZeroShotClassification``, ``TokenClassification``, ``FillMask``, ``QuestionAnswering``,
  ``TableQuestionAnswering``, ``ImageSegmentation`` and ``ObjectDetection``: the task of
  the same name
* ``Summarization``, ``Translation`` and ``SpeechRecognition``
  (``automatic-speech-recognition``): return text
* ``TextToImage``: ``text-to-image``, returns a binary image

Classification, question answering, fill mask, segmentation and detection return an object
of the ``Symfony\AI\Platform\Bridge\HuggingFace\Output`` namespace. Use
``$result->asObject()`` to get it.

Options of the invocation are sent as the ``parameters`` of the task::

    $result = $platform->invoke('facebook/bart-large-mnli', $text, [
        'candidate_labels' => ['refund', 'legal', 'faq'],
    ]);

The `Hugging Face examples`_ show one invocation per task.

Model Catalog
-------------

The default ``ModelCatalog`` lists the chat models of the inference providers and the most
downloaded models of each task. It doesn't send any request. The list is generated from the
Hugging Face Hub API and updated regularly. The capabilities of a chat model combine the features of all providers that
serve it.

To use any other model, resolve models through the Hugging Face Hub API with the
``ModelApiCatalog``::

    use Symfony\AI\Platform\Bridge\HuggingFace\ApiClient;
    use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
    use Symfony\AI\Platform\Bridge\HuggingFace\ModelApiCatalog;
    use Symfony\AI\Platform\Bridge\HuggingFace\Provider;

    // the provider must be the same as the one of the platform
    $modelCatalog = new ModelApiCatalog(new ApiClient($httpClient, 'hf_your_api_key_here'), Provider::NSCALE);

    $platform = Factory::createPlatform('hf_your_api_key_here', Provider::NSCALE, $httpClient, $modelCatalog);

The ``ModelApiCatalog`` sends one request per model and keeps the result for the lifetime of
the catalog. It only returns chat models that the configured provider serves, with the
features of this provider. If the Hub doesn't serve the model for a supported task, it throws
a :class:`Symfony\\AI\\Platform\\Exception\\ModelNotFoundException`.

In the AI Bundle, enable the ``api`` option of the platform's ``model_catalog``, see
:ref:`ai-bundle-model-catalog`:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            huggingface:
                api_key: '%env(HUGGINGFACE_API_KEY)%'
                # Hugging Face picks the provider for each chat request when null
                provider: null
                model_catalog:
                    # resolve models through the Hugging Face Hub API
                    api: true

.. _`Hugging Face Inference Providers`: https://huggingface.co/docs/inference-providers/index
.. _`access token`: https://huggingface.co/docs/hub/en/security-tokens
.. _`function calling guide`: https://huggingface.co/docs/inference-providers/guides/function-calling
.. _`Hugging Face tool calling example`: https://github.com/symfony/ai/blob/main/examples/huggingface/toolcall.php
.. _`Hugging Face examples`: https://github.com/symfony/ai/tree/main/examples/huggingface
