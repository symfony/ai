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
use Symfony\AI\Fixtures\TemperatureTool;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$temperature = new TemperatureTool();
$toolbox = new Toolbox([$temperature], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'claude-sonnet-4-5-20250929', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('Use the available tool to answer. Call it once per city, in a single turn.'),
    Message::ofUser('What is the temperature in Berlin, Paris and Rome?'),
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL.\PHP_EOL;

// Claude requests all three cities in a single turn, so the tool runs three times.
echo 'Tool executed for: '.implode(', ', $temperature->calledFor).\PHP_EOL;
