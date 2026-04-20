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
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = OpenAiFactory::createPlatform(env('OPENAI_API_KEY'), httpClient: http_client());
$agent = new Agent($platform, 'gpt-4o');

$speechAgent = new SpeechAgent($agent, configuration: new SpeechConfiguration(
    sttModel: 'whisper-1',
), speechToTextPlatform: $platform);

$answer = $speechAgent->call(new MessageBag(
    Message::ofUser(Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'))
));

echo $answer->getContent().\PHP_EOL;
