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
use Symfony\AI\Agent\Toolbox\Tool\Wikipedia;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['AWS_ACCESS_KEY_ID'], $_SERVER['AWS_SECRET_ACCESS_KEY'], $_SERVER['AWS_DEFAULT_REGION'])
) {
    echo 'Please set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY and AWS_DEFAULT_REGION environment variables.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create();
$model = new Claude('claude-3-7-sonnet-20250219');

$wikipedia = new Wikipedia(HttpClient::create());
$toolbox = new Toolbox([$wikipedia]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('Who is the current chancellor of Germany?'));
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
