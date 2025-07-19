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
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['OPENAI_API_KEY'], $_SERVER['BRAVE_API_KEY'])) {
    echo 'Please set the OPENAI_API_KEY and BRAVE_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}
$platform = PlatformFactory::create($_SERVER['OPENAI_API_KEY']);
$model = new GPT(GPT::GPT_4O_MINI);

$httpClient = HttpClient::create();
$brave = new Brave($httpClient, $_SERVER['BRAVE_API_KEY']);
$crawler = new Crawler($httpClient);
$toolbox = new Toolbox([$brave, $crawler]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('What was the latest game result of Dallas Cowboys?'));
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
