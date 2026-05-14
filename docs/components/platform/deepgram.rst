Deepgram
========

Deepgram offers fast, accurate speech-to-text (STT) and text-to-speech (TTS) models. The Symfony AI
Platform component bridges both Deepgram REST endpoints (``/v1/listen``, ``/v1/speak``) and the
streaming WebSocket endpoints (``wss://api.deepgram.com/v1/listen``, ``wss://api.deepgram.com/v1/speak``).

For comprehensive information about Deepgram, see the `Deepgram API reference`_.

Installation
------------

To use Deepgram with Symfony AI Platform, install the bridge:

.. code-block:: terminal

    $ composer require symfony/ai-deepgram-platform

The WebSocket transport pulls in `amphp/websocket-client`_, which is declared as a hard dependency
of the bridge.

Setup
-----

Authentication
~~~~~~~~~~~~~~

Deepgram requires an API key, which you can create from the `Deepgram console`_. Configure it in your
environment file:

.. code-block:: bash

    DEEPGRAM_API_KEY=your-deepgram-api-key

The key is sent as ``Authorization: Token <key>`` on every REST and WebSocket request.

Usage
-----

Text-to-speech (REST)
~~~~~~~~~~~~~~~~~~~~~

Pass the voice model name and a :class:`Symfony\\AI\\Platform\\Message\\Content\\Text` instance::

    use Symfony\AI\Platform\Bridge\Deepgram\PlatformFactory;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = PlatformFactory::createPlatform($_ENV['DEEPGRAM_API_KEY']);

    $result = $platform->invoke('aura-2-thalia-en', new Text('Hello world'));
    file_put_contents('/tmp/out.mp3', $result->asBinary());

Audio knobs supported by Deepgram (``encoding``, ``container``, ``sample_rate``, ``bit_rate``) are
forwarded as query-string parameters when you pass them as invocation options::

    $platform->invoke(
        'aura-2-thalia-en',
        new Text('Hello world'),
        ['encoding' => 'linear16', 'sample_rate' => 24000],
    );

Speech-to-text (REST)
~~~~~~~~~~~~~~~~~~~~~

Pass an :class:`Symfony\\AI\\Platform\\Message\\Content\\Audio` instance. The bridge uploads the raw
bytes with the right ``Content-Type`` header — there is no base64 round-trip::

    use Symfony\AI\Platform\Bridge\Deepgram\PlatformFactory;
    use Symfony\AI\Platform\Message\Content\Audio;

    $platform = PlatformFactory::createPlatform($_ENV['DEEPGRAM_API_KEY']);

    $result = $platform->invoke('nova-3', Audio::fromFile('/path/to/audio.mp3'));
    echo $result->asText().PHP_EOL;

Deepgram STT options (``language``, ``smart_format``, ``punctuate``, ``diarize``, ``utterances``…) are
forwarded as query parameters::

    $platform->invoke(
        'nova-3',
        Audio::fromFile('/path/to/audio.mp3'),
        ['smart_format' => 'true', 'language' => 'en', 'diarize' => 'true'],
    );

URL-based transcription
.......................

You can also transcribe a remote audio file by passing the URL directly. The bridge validates that
the scheme is ``http`` or ``https`` and rejects ``data:``/``file:`` URLs::

    $platform->invoke(
        'nova-3',
        [
            'type' => 'input_audio',
            'input_audio' => ['url' => 'https://example.com/audio.mp3'],
        ],
    );

WebSocket transport
~~~~~~~~~~~~~~~~~~~

Set ``useWebsockets: true`` on the factory to route both TTS and STT through the streaming
WebSocket endpoint::

    use Symfony\AI\Platform\Bridge\Deepgram\PlatformFactory;
    use Symfony\AI\Platform\Message\Content\Text;

    $platform = PlatformFactory::createPlatform(
        $_ENV['DEEPGRAM_API_KEY'],
        useWebsockets: true,
    );

    $result = $platform->invoke('aura-2-thalia-en', new Text('Hello world'));
    file_put_contents('/tmp/out.mp3', $result->asBinary());

The bridge accumulates every binary frame for TTS until the server emits a ``Flushed`` control
message, then sends ``Close`` to release the connection.

For STT, the audio bytes are sent in a single binary frame, followed by ``CloseStream``. All ``Results``
messages with ``is_final: true`` are concatenated into a single transcript.

Idle keep-alive
...............

Deepgram closes idle WebSockets after ~10 seconds. The bridge schedules a ``KeepAlive`` control
message every 5 seconds during STT sessions; you can change the cadence (or disable it by passing
``0.0``) when you instantiate the WebSocket client directly::

    use Symfony\AI\Platform\Bridge\Deepgram\Websocket\AmpWebsocketConnector;
    use Symfony\AI\Platform\Bridge\Deepgram\WebsocketClient;

    $client = new WebsocketClient(
        'wss://api.deepgram.com/v1',
        $_ENV['DEEPGRAM_API_KEY'],
        new AmpWebsocketConnector(),
        keepAliveInterval: 3.0,
    );

Custom WebSocket connector
..........................

For testing or for routing through a custom amphp client, inject a
:class:`Symfony\\AI\\Platform\\Bridge\\Deepgram\\Websocket\\WebsocketConnectorInterface` into the
factory::

    $platform = PlatformFactory::createPlatform(
        $_ENV['DEEPGRAM_API_KEY'],
        useWebsockets: true,
        websocketConnector: $myConnector,
    );

Examples
--------

See the ``examples/deepgram/`` directory for complete working examples:

* ``text-to-speech.php`` - TTS over REST, audio piped to stdout
* ``speech-to-text.php`` - STT over REST, transcript written to stdout
* ``text-to-speech-as-websocket.php`` - TTS over the streaming WebSocket transport

.. _Deepgram API reference: https://developers.deepgram.com/reference/deepgram-api-overview
.. _Deepgram console: https://console.deepgram.com/
.. _amphp/websocket-client: https://github.com/amphp/websocket-client
