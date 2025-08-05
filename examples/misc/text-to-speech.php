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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\ElevenLabs;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new Gpt(Gpt::GPT_4O_MINI);

$elevenLabs = new ElevenLabs(
    http_client(),
    env('ELEVENLABS_API_KEY'),
    __DIR__.'/../tmp',
    'eleven_multilingual_v2',
    'Dslrhjl3ZpzrctukrQSN' // Brad (https://elevenlabs.io/app/voice-library?voiceId=Dslrhjl3ZpzrctukrQSN)
);

$toolbox = new Toolbox([$elevenLabs], logger: logger());
$toolProcessor = new AgentProcessor($toolbox);

$agent = new Agent($platform, $model, inputProcessors: [$toolProcessor], outputProcessors: [$toolProcessor]);

$messages = new MessageBag(Message::ofUser('Convert the following text to voice: "Hello world with voice!"'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
