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
use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
use Symfony\AI\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;
use Symfony\AI\Platform\Bridge\DeepSeek\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('DEEPSEEK_API_KEY'), http_client());
$model = new DeepSeek(DeepSeek::CHAT);

$processor = new AgentProcessor();
$agent = new Agent($platform, $model, [$processor], [$processor], logger: logger());
// todo: this example does not work with ['output_structure' => MathReasoning::class] out of the box
$messages = new MessageBag(
    Message::forSystem('You are a helpful math tutor. Guide the user through the solution step by step.'),
    Message::ofUser('how can I solve 8x + 7 = -23'),
);
$result = $agent->call($messages, ['output_structure' => MathReasoning::class]);

dump($result->getContent());
