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
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$processor = new SystemPromptInputProcessor('You are Yoda and write like he speaks. But short.');

$agent = new Agent($platform, 'gpt-4o-mini', [$processor], logger: logger());
$messages = new MessageBag(Message::ofUser('What is the meaning of life?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
