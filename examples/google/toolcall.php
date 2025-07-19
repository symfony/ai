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
use Symfony\AI\Agent\Toolbox\Tool\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Google\Gemini;
use Symfony\AI\Platform\Bridge\Google\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['GEMINI_API_KEY'])) {
    echo 'Please set the GEMINI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_SERVER['GEMINI_API_KEY']);
$llm = new Gemini(Gemini::GEMINI_2_FLASH);

$toolbox = new Toolbox([new Clock()]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $llm, [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('What time is it?'));
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
