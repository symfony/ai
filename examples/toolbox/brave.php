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
use Symfony\AI\Agent\Toolbox\Tool\Brave;
use Symfony\AI\Agent\Toolbox\Tool\Crawler;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new GPT(GPT::GPT_4O_MINI);

$brave = new Brave(http_client(), env('BRAVE_API_KEY'));
$crawler = new Crawler(http_client());
$toolbox = new Toolbox([$brave, $crawler], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor], logger());

$messages = new MessageBag(Message::ofUser('What was the latest game result of Dallas Cowboys?'));
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
