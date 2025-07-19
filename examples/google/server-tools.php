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

// Available server-side tools as of 2025-06-28: url_context, google_search, code_execution
$llm = new Gemini('gemini-2.5-pro-preview-03-25', ['server_tools' => ['url_context' => true], 'temperature' => 1.0]);

$toolbox = new Toolbox([new Clock()]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $llm);

$messages = new MessageBag(
    Message::ofUser(
        <<<'PROMPT'
            What was the 12 month Euribor rate a week ago based on https://www.euribor-rates.eu/en/current-euribor-rates/4/euribor-rate-12-months/
            PROMPT,
    ),
);

$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
