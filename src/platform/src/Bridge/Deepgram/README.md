Deepgram Platform
=================

Deepgram platform bridge for Symfony AI. Supports text-to-speech (TTS) and speech-to-text (STT)
over both HTTP and WebSocket transports.

Quick start
-----------

```php
use Symfony\AI\Platform\Bridge\Deepgram\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;

$platform = PlatformFactory::createPlatform(apiKey: $_ENV['DEEPGRAM_API_KEY']);

// Text-to-speech (HTTP)
$audio = $platform->invoke('aura-2-thalia-en', new Text('Hello world'))->asBinary();

// Speech-to-text (HTTP, raw audio bytes uploaded with proper Content-Type)
$transcript = $platform->invoke('nova-3', Audio::fromFile('/path/to/audio.mp3'))->asText();

// Text-to-speech over WebSocket
$wsPlatform = PlatformFactory::createPlatform(apiKey: $_ENV['DEEPGRAM_API_KEY'], useWebsockets: true);
$audio = $wsPlatform->invoke('aura-2-thalia-en', new Text('Hello world'))->asBinary();
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
