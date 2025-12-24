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
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsSpeechPlatform;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Speech\SpeechListener;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$elevenLabsPlatform = new ElevenLabsSpeechPlatform(
    PlatformFactory::create(
        apiKey: env('ELEVEN_LABS_API_KEY'),
        httpClient: http_client(),
    ),
    [
        'tts_model' => 'eleven_multilingual_v2',
        'tts_voice' => 'Dslrhjl3ZpzrctukrQSN', // Brad (https://elevenlabs.io/app/voice-library?voiceId=Dslrhjl3ZpzrctukrQSN)
    ],
);

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addSubscriber(new SpeechListener([
    'elevenlabs' => $elevenLabsPlatform,
]));

$platform = OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client(), eventDispatcher: $eventDispatcher);

$agent = new Agent($platform, 'gpt-4o');
$answer = $agent->call(new MessageBag(
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
));

echo $answer->getSpeech('elevenlabs')->asBinary();
