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
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Speech\SpeechConfiguration;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client());

$agent = new Agent($platform, 'gpt-4o', [
    new SpeechProcessor(ElevenLabsPlatformFactory::create(
        apiKey: env('ELEVEN_LABS_API_KEY'),
        httpClient: http_client(),
    ), new SpeechConfiguration([
        'stt_model' => 'scribe_v1',
    ])),
]);
$answer = $agent->call(new MessageBag(
    Message::ofUser(Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'))
));

echo $answer->getContent();
