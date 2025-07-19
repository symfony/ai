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
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Memory\MemoryInputProcessor;
use Symfony\AI\Agent\Memory\StaticMemoryProvider;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!$_ENV['OPENAI_API_KEY']) {
    echo 'Please set the OPENAI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
$model = new GPT(GPT::GPT_4O_MINI);

$systemPromptProcessor = new SystemPromptInputProcessor('You are a professional trainer with short, personalized advices and a motivating claim.');

$personalFacts = new StaticMemoryProvider(
    'My name is Wilhelm Tell',
    'I wish to be a swiss national hero',
    'I am struggling with hitting apples but want to be professional with the bow and arrow',
);
$memoryProcessor = new MemoryInputProcessor($personalFacts);

$chain = new Agent($platform, $model, [$systemPromptProcessor, $memoryProcessor]);
$messages = new MessageBag(Message::ofUser('What do we do today?'));
$result = $chain->call($messages);

echo $result->getContent().\PHP_EOL;
