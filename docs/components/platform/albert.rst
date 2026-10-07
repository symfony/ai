Albert
======

Albert is the sovereign AI gateway of the French public administration, built by `Etalab`_
on top of `OpenGateLLM`_. It exposes an OpenAI-compatible API, so the bridge builds on the
Generic one and adds what is specific to Albert.

Setup
-----

Albert requires an API key and the URL of the instance to talk to, without the API
version::

    use Symfony\AI\Platform\Bridge\Albert\Factory;

    $platform = Factory::createPlatform(
        $apiKey,
        'https://albert.api.etalab.gouv.fr',
        $httpClient, // optional
    );

    $result = $platform->invoke('openweight-small', $messages);

Environmental Impacts
---------------------

Next to the token usage, Albert reports the environmental impacts of a call: an
estimated energy consumption (``kWh``) and greenhouse gas emissions
(``kgCO2eq``) it caused. The bridge exposes them as ``impacts`` result metadata, in the shape
the API returns it::

    $result = $platform->invoke('openweight-small', $messages);
    $result->asText();

    $impacts = $result->getMetadata()->get('impacts');

    $impacts['kWh'];     // 5.7095659415205885e-06
    $impacts['kgCO2eq']; // 4.5809521528648614e-07

Albert reports the environmental impacts on the last event of a streamed response, together with the
token usage of the call. Both are therefore only available once the stream has been fully
consumed -- they are not part of the deltas the stream yields, but of the metadata of the
result that produced them::

    use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

    $result = $platform->invoke('openweight-small', $messages, ['stream' => true]);

    foreach ($result->asStream() as $delta) {
        if ($delta instanceof TextDelta) {
            echo $delta;
        }
    }

    $usage = $result->getMetadata()->get('token_usage');
    $impacts = $result->getMetadata()->get('impacts');

.. note::

    The metadata is only set when the instance reports environmental impacts for the response, so
    guard against ``null``.

Examples
--------

See the ``examples/albert/`` directory for complete working examples:

* ``chat.php`` - Answering a question grounded in a document context
* ``embeddings.php`` - Embedding a text
* ``stream-impacts.php`` - Streaming a response and reading its token usage and environmental impacts

.. _Etalab: https://www.etalab.gouv.fr/
.. _OpenGateLLM: https://github.com/etalab-ia/OpenGateLLM
