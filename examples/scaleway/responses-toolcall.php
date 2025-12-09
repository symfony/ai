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
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Platform\Bridge\Scaleway\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\Clock;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('SCALEWAY_SECRET_KEY'), http_client());

// Create a simple clock tool for demonstration
$metadataFactory = (new MemoryToolFactory())
    ->addTool(Clock::class, 'clock', 'Get the current date and time', 'now');
$toolbox = new Toolbox([new Clock()], $metadataFactory, logger: logger());
$processor = new AgentProcessor($toolbox);

// gpt-oss-120b uses Scaleway Responses API which supports function calling
$agent = new Agent($platform, 'gpt-oss-120b', [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('What date and time is it right now?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
