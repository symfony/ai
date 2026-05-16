Inworld
=======

Inworld provides text-to-speech (TTS) and speech-to-text (STT) models accessible through a REST API. The
Symfony AI Platform component ships a bridge for synchronous synthesis, streaming synthesis (line-delimited
JSON) and transcription.

For comprehensive information about Inworld, see the `Inworld API reference`_.

Setup
-----

Authentication
~~~~~~~~~~~~~~

Inworld authenticates REST API requests with the Base64 API key issued from the `Inworld Portal`_. The
bridge attaches it as an ``Authorization: Basic <api-key>`` header.

.. code-block:: bash

    INWORLD_API_KEY=...

Custom HTTP Client
~~~~~~~~~~~~~~~~~~

The factory accepts any ``Symfony\Contracts\HttpClient\HttpClientInterface`` instance, which lets you wire
custom timeouts, retries, proxies, or instrumentation::

    use Symfony\AI\Platform\Bridge\Inworld\Factory;
    use Symfony\Component\HttpClient\HttpClient;
    use Symfony\Component\HttpClient\RetryableHttpClient;

    $httpClient = new RetryableHttpClient(
        HttpClient::create(['timeout' => 30, 'max_duration' => 60]),
    );

    $platform = Factory::createPlatform(
        apiKey: $_ENV['INWORLD_API_KEY'],
        httpClient: $httpClient,
    );

You can also provide a preconfigured ``ScopingHttpClient`` that already carries the ``Authorization`` header
and the base URI — in that case omit the ``apiKey`` argument and the bridge skips its own header attachment::

    use Symfony\AI\Platform\Bridge\Inworld\Factory;
    use Symfony\Component\HttpClient\HttpClient;
    use Symfony\Component\HttpClient\ScopingHttpClient;

    $httpClient = ScopingHttpClient::forBaseUri(
        HttpClient::create(),
        'https://api.inworld.ai/',
        ['headers' => ['Authorization' => 'Basic '.$_ENV['INWORLD_API_KEY']]],
    );

    $platform = Factory::createPlatform(httpClient: $httpClient);

In the bundle, point the ``http_client`` option at any service that implements
``HttpClientInterface``:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            inworld:
                http_client: 'ai.inworld'

Usage
-----

Text-to-Speech (synchronous)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Synchronous TTS returns a ``BinaryResult`` containing decoded MP3 bytes::

    use Symfony\AI\Platform\Bridge\Inworld\Factory;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = Factory::createPlatform(apiKey: $_ENV['INWORLD_API_KEY']);

    $result = $platform->invoke('inworld-tts-2', new Text('The first move is what sets everything in motion.'), [
        'voice' => 'Dennis',
    ]);

    file_put_contents('/tmp/speech.mp3', $result->asBinary());

Available models: ``inworld-tts-1``, ``inworld-tts-1-max``, ``inworld-tts-1.5-mini``, ``inworld-tts-1.5-max``,
``inworld-tts-2`` (the catalog is also fetched dynamically from the Inworld API and may include more entries).

Common options:

* ``voice`` (required) — voice identifier (e.g. ``Dennis``).
* ``audioConfig`` — audio encoding settings; defaults to ``{ audioEncoding: 'MP3', sampleRateHertz: 48000 }``.
* ``language`` — BCP-47 tag (``en-US``, ``fr-FR``, ``ja-JP``, …); auto-detected when omitted.
* ``temperature`` — float in ``]0, 2]``; default ``1.0``.
* ``deliveryMode`` — ``STABLE``, ``BALANCED``, or ``CREATIVE`` (TTS-2 only).

Text-to-Speech (streaming)
~~~~~~~~~~~~~~~~~~~~~~~~~~

Set ``stream: true`` to receive a ``StreamResult`` yielding ``BinaryDelta`` chunks as the audio is generated::

    use Symfony\AI\Platform\Bridge\Inworld\Factory;
    use Symfony\AI\Platform\Message\Content\Text;
    use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;

    $platform = Factory::createPlatform(apiKey: $_ENV['INWORLD_API_KEY']);

    $result = $platform->invoke('inworld-tts-2', new Text('The first move is what sets everything in motion.'), [
        'voice' => 'Dennis',
        'stream' => true,
    ]);

    foreach ($result->asStream() as $chunk) {
        if ($chunk instanceof BinaryDelta) {
            echo $chunk->getData();
        }
    }

The bridge consumes the Inworld NDJSON stream, decodes each chunk's base64 ``audioContent``, and yields the
raw bytes through ``BinaryDelta``. Pipe the output directly to a player or aggregate it into a file.

Speech-to-Text
~~~~~~~~~~~~~~

Pass an ``Audio`` content object to a transcription model to obtain a ``TextResult``::

    use Symfony\AI\Platform\Bridge\Inworld\Factory;
    use Symfony\AI\Platform\Message\Content\Audio;

    $platform = Factory::createPlatform(apiKey: $_ENV['INWORLD_API_KEY']);

    $result = $platform->invoke(
        'inworld/inworld-stt-1',
        Audio::fromFile('/path/to/audio.mp3'),
    );

    echo $result->asText();

Available STT models: ``inworld/inworld-stt-1``, ``groq/whisper-large-v3``.

Common options (placed under ``transcribeConfig`` by the bridge):

* ``audioEncoding`` — ``AUTO_DETECT`` (default), ``LINEAR16``, ``MP3``, ``OGG_OPUS``, ``FLAC``.
* ``language`` — BCP-47 tag; auto-detected when omitted.
* ``sampleRateHertz`` — defaults to ``16000``.
* ``includeWordTimestamps`` — boolean; default ``false``.

Bundle Configuration
--------------------

When the bridge is exposed through ``symfony/ai-bundle``, configure it in your application:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            inworld:
                api_key: '%env(INWORLD_API_KEY)%'

The resulting service ``ai.platform.inworld`` is autowireable as ``PlatformInterface $inworld`` and is tagged
``ai.platform.speech``, so it can be used as ``text_to_speech_platform`` or ``speech_to_text_platform`` in the
agent ``speech:`` configuration.

Examples
--------

See the ``examples/inworld/`` directory for complete working examples:

* ``text-to-speech.php`` — Synchronous synthesis to MP3 bytes
* ``text-to-speech-as-stream.php`` — Streaming synthesis with ``BinaryDelta`` iteration
* ``speech-to-text.php`` — Transcription from a local audio file

.. _Inworld API reference: https://docs.inworld.ai/api-reference/introduction
.. _Inworld Portal: https://platform.inworld.ai/
