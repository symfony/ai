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
        $_ENV['ALBERT_API_KEY'],
        'https://albert.api.etalab.gouv.fr',
        $httpClient, // optional
    );

    $result = $platform->invoke('openweight-small', $messages);

Carbon Footprint
----------------

Next to the token usage, Albert reports the environmental footprint of a call: an
estimated range of the energy consumption (``kWh``) and of the greenhouse gas emission
(``kgCO2eq``) it caused. The bridge exposes it as ``carbon`` result metadata, in the shape
the API returns it::

    $result = $platform->invoke('openweight-small', $messages);
    $result->asText();

    $carbon = $result->getMetadata()->get('carbon');

    $carbon['kWh']['min'];     // 5.7095659415205885e-06
    $carbon['kgCO2eq']['max']; // 4.5809521528648614e-07

Albert reports the footprint on the last event of a streamed response, together with the
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
    $carbon = $result->getMetadata()->get('carbon');

.. note::

    The metadata is only set when the instance reports a footprint for the response, so
    guard against ``null``.

Examples
--------

See the ``examples/albert/`` directory for complete working examples:

* ``chat.php`` - Answering a question grounded in a document context
* ``embeddings.php`` - Embedding a text
* ``stream-carbon.php`` - Streaming a response and reading its token usage and footprint

.. _Etalab: https://www.etalab.gouv.fr/
.. _OpenGateLLM: https://github.com/etalab-ia/OpenGateLLM
