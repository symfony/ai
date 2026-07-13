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
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

// Load system prompt from a plain text file (.txt)
$promptFile = File::fromFile(dirname(__DIR__, 2).'/fixtures/prompts/helpful-assistant.txt');

$agent = new Agent($platform, 'gpt-5-mini', instruction: $promptFile);
$messages = new MessageBag(Message::ofUser('Can you explain what dependency injection is?'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;
