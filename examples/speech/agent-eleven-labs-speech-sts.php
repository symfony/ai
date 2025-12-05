<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsSpeechListener;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsSpeechProvider;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Speech\SpeechConfiguration;
use Symfony\AI\Platform\Speech\SpeechProviderListener;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addSubscriber(new SpeechProviderListener([
    new ElevenLabsSpeechProvider(PlatformFactory::create(
        apiKey: env('ELEVEN_LABS_API_KEY'),
        httpClient: http_client(),
        speechConfiguration: new SpeechConfiguration(
            ttsModel: 'eleven_multilingual_v2',
            ttsVoice: 'Dslrhjl3ZpzrctukrQSN', // Brad (https://elevenlabs.io/app/voice-library?voiceId=Dslrhjl3ZpzrctukrQSN)
            sttModel: 'eleven_multilingual_v2'
        )),
    ),
], [
    new ElevenLabsSpeechListener(PlatformFactory::create(
        apiKey: env('ELEVEN_LABS_API_KEY'),
        httpClient: http_client(),
        speechConfiguration: new SpeechConfiguration(
            sttModel: 'scribe_v1'
        )),
    ),
]));

$platform = OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client(), eventDispatcher: $eventDispatcher);

$agent = new Agent($platform, 'gpt-4o');
$answer = $agent->call(new MessageBag(
    Message::ofUser(Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'))
));

echo $answer->getSpeech('eleven_labs')->asBinary();
