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

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());

// Available server-side tools as of 2025-06-28: url_context, google_search, code_execution
$llm = new Gemini('gemini-2.5-pro-preview-03-25', ['server_tools' => ['url_context' => true], 'temperature' => 1.0]);

$toolbox = new Toolbox([new Clock()], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $llm, logger: logger());

$messages = new MessageBag(
    Message::ofUser(
        <<<'PROMPT'
            What was the 12 month Euribor rate a week ago based on https://www.euribor-rates.eu/en/current-euribor-rates/4/euribor-rate-12-months/
            PROMPT,
    ),
);

$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
