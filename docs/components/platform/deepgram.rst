Deepgram
========

Deepgram offers fast, accurate speech-to-text (STT) and text-to-speech (TTS) models. The Symfony AI
Platform component bridges the Deepgram REST endpoints (``/v1/listen``, ``/v1/speak``).

For comprehensive information about Deepgram, see the `Deepgram API reference`_.

Installation
------------

To use Deepgram with Symfony AI Platform, install the bridge:

.. code-block:: terminal

    $ composer require symfony/ai-deepgram-platform

Setup
-----

Authentication
~~~~~~~~~~~~~~

Deepgram requires an API key, which you can create from the `Deepgram console`_. Configure it in your
environment file:

.. code-block:: bash

    DEEPGRAM_API_KEY=your-deepgram-api-key

The key is sent as ``Authorization: Token <key>`` on every request.

Alternatively, you can omit the API key and pass an HTTP client that is already configured with a
base URI and the ``Authorization`` header. In that case the ``endpoint`` argument is ignored, as
the pre-configured client is used as-is.

Usage
-----

Model names are validated against the live ``/v1/models`` endpoint: the catalog is fetched over
HTTP on the first invocation and memoized per platform instance. Both the canonical model names
(``aura-2-thalia-en``, ``nova-3-general``) and the speech-to-text architecture aliases
(``nova-3``, ``nova-2``, ``whisper``) are accepted.

Text-to-speech
~~~~~~~~~~~~~~

Pass the voice model name and a :class:`Symfony\\AI\\Platform\\Message\\Content\\Text` instance::

    use Symfony\AI\Platform\Bridge\Deepgram\Factory;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = Factory::createPlatform(apiKey: $_ENV['DEEPGRAM_API_KEY']);

    $result = $platform->invoke('aura-2-thalia-en', new Text('Hello world'));
    file_put_contents('/tmp/out.mp3', $result->asBinary());

Audio knobs supported by Deepgram (``encoding``, ``container``, ``sample_rate``, ``bit_rate``) are
forwarded as query-string parameters when you pass them as invocation options::

    $platform->invoke(
        'aura-2-thalia-en',
        new Text('Hello world'),
        [PlatformOptions::PROVIDER_OPTIONS => ['encoding' => 'linear16', 'sample_rate' => 24000]],
    );

Streaming text-to-speech
........................

Pass the ``stream`` option to consume the audio progressively while Deepgram generates it::

    use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;

    $result = $platform->invoke('aura-2-thalia-en', new Text('Hello world'), ['stream' => true]);

    foreach ($result->asStream() as $chunk) {
        if ($chunk instanceof BinaryDelta) {
            echo $chunk->getData();
        }
    }

Speech-to-text
~~~~~~~~~~~~~~

Pass an :class:`Symfony\\AI\\Platform\\Message\\Content\\Audio` instance. The bridge uploads the
audio as raw bytes with the right ``Content-Type`` header — files are streamed from disk, so even
large recordings do not have to fit in memory::

    use Symfony\AI\Platform\Bridge\Deepgram\Factory;
    use Symfony\AI\Platform\Message\Content\Audio;

    $platform = Factory::createPlatform(apiKey: $_ENV['DEEPGRAM_API_KEY']);

    $result = $platform->invoke('nova-3', Audio::fromFile('/path/to/audio.mp3'));
    echo $result->asText().PHP_EOL;

Deepgram STT options (``language``, ``smart_format``, ``punctuate``, ``diarize``, ``utterances``…) are
forwarded as query parameters::

    $platform->invoke(
        'nova-3',
        Audio::fromFile('/path/to/audio.mp3'),
        [PlatformOptions::PROVIDER_OPTIONS => ['smart_format' => 'true', 'language' => 'en', 'diarize' => 'true']],
    );

Use ``'language' => 'multi'`` to enable multilingual (code-switching) transcription on ``nova-3``
models.

URL-based transcription
.......................

You can also transcribe a remote audio file by passing the URL directly. The bridge validates that
the scheme is ``http`` or ``https`` and rejects ``data:``/``file:`` URLs::

    $platform->invoke(
        'nova-3-general',
        [
            'type' => 'input_audio',
            'input_audio' => ['url' => 'https://example.com/audio.mp3'],
        ],
    );

Examples
--------

See the ``examples/deepgram/`` directory for complete working examples:

* ``text-to-speech.php`` - TTS, audio piped to stdout
* ``speech-to-text.php`` - STT, transcript written to stdout

.. _Deepgram API reference: https://developers.deepgram.com/reference/deepgram-api-overview
.. _Deepgram console: https://console.deepgram.com/
