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
$llm = new Gemini(Gemini::GEMINI_2_FLASH);

$toolbox = new Toolbox([new Clock()], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $llm, [$processor], [$processor], logger());

$messages = new MessageBag(Message::ofUser('What time is it?'));
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
