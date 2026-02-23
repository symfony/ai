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
use Symfony\AI\Agent\Capability\CapabilityHandlerRegistry;
use Symfony\AI\Agent\Capability\DelayCapabilityHandler;
use Symfony\AI\Agent\Capability\InputDelayCapability;
use Symfony\AI\Agent\InputProcessor\CapabilityProcessor;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$agent = new Agent($platform, 'gpt-4o-mini', inputProcessors: [
    new CapabilityProcessor(new CapabilityHandlerRegistry([
        new DelayCapabilityHandler(),
    ])),
]);
$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
);

$result = $agent->call($messages, capabilities: [
    new InputDelayCapability(rand(1, 5)),
]);

echo $result->getContent().\PHP_EOL;
