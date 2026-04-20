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
use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Agent\SpeechAgent;
use Symfony\AI\Platform\Bridge\ElevenLabs\Factory as ElevenLabsFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\BinaryResult;

require_once dirname(__DIR__).'/bootstrap.php';

$openAIPlatform = OpenAiFactory::createPlatform(env('OPENAI_API_KEY'), httpClient: http_client());
$agent = new Agent($openAIPlatform, 'gpt-4o');

$elevenLabsPlatform = ElevenLabsFactory::createPlatform(
    apiKey: env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client(),
);

$speechAgent = new SpeechAgent($agent, new SpeechConfiguration(
    ttsModel: 'eleven_multilingual_v2',
    ttsOptions: [
        'voice' => 'pqHfZKP75CvOlQylNhV4', // Bill
    ],
    sttModel: 'whisper-1',
), $openAIPlatform, $elevenLabsPlatform);

$answer = $speechAgent->call(new MessageBag(
    Message::ofUser(Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'))
));

assert($answer instanceof BinaryResult);

echo $answer->getMetadata()->get('text').\PHP_EOL;
$answer->asFile('/tmp/speech.mp3');
output()->writeln('Audio content saved to <comment>/tmp/speech.mp3</comment>');
