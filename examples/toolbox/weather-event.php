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
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Tool\OpenMeteo;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Response\ObjectResponse;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!$_ENV['OPENAI_API_KEY']) {
    echo 'Please set the OPENAI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
$model = new GPT(GPT::GPT_4O_MINI);

$openMeteo = new OpenMeteo(HttpClient::create());
$toolbox = Toolbox::create($openMeteo);
$eventDispatcher = new EventDispatcher();
$processor = new AgentProcessor($toolbox, eventDispatcher: $eventDispatcher);
$agent = new Agent($platform, $model, [$processor], [$processor]);

// Add tool call result listener to enforce chain exits direct with structured response for weather tools
$eventDispatcher->addListener(ToolCallsExecuted::class, function (ToolCallsExecuted $event): void {
    foreach ($event->toolCallResults as $toolCallResult) {
        if (str_starts_with($toolCallResult->toolCall->name, 'weather_')) {
            $event->response = new ObjectResponse($toolCallResult->result);
        }
    }
});

$messages = new MessageBag(Message::ofUser('How is the weather currently in Berlin?'));
$response = $agent->call($messages);

dump($response->getContent());
