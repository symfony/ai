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
use Symfony\AI\Agent\InputProcessor\SpeechProcessor;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory as ElevenLabsPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Speech\SpeechConfiguration;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client());

$agent = new Agent($platform, 'gpt-4o', outputProcessors: [
    new SpeechProcessor(ElevenLabsPlatformFactory::create(
        env('ELEVEN_LABS_API_KEY'),
        httpClient: http_client()
    ), new SpeechConfiguration([
        'tts_model' => 'eleven_multilingual_v2',
        'tts_options' => [
            'voice' => 'Dslrhjl3ZpzrctukrQSN', // Brad (https://elevenlabs.io/app/voice-library?voiceId=Dslrhjl3ZpzrctukrQSN)
        ],
    ])),
]);
$answer = $agent->call(new MessageBag(
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
));

echo $answer->getSpeech()->asBinary();
