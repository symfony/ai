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
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('HUGGINGFACE_KEY'), Provider::CEREBRAS, http_client());

$toolbox = new Toolbox([new Clock(clock())], logger: logger());
$agent = new Agent($platform, 'openai/gpt-oss-120b', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('What is the current date and time?'));
$result = $agent->call($messages, ['task' => Task::CHAT_COMPLETION]);

echo $result->asText().\PHP_EOL;
