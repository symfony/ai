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
use Symfony\AI\Agent\Toolbox\Tool\SerpApi;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new GPT(GPT::GPT_4O_MINI);

$serpApi = new SerpApi(http_client(), env('SERP_API_KEY'));
$toolbox = new Toolbox([$serpApi], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor], logger());

$messages = new MessageBag(Message::ofUser('Who is the current chancellor of Germany?'));
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
