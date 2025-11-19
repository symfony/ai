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
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY'], http_client());

// Load system prompt from a JSON file
$promptFile = File::fromFile(dirname(__DIR__, 2).'/fixtures/prompts/code-reviewer.json');
$systemPromptProcessor = new SystemPromptInputProcessor($promptFile);

$agent = new Agent($platform, 'gpt-4o-mini', [$systemPromptProcessor]);
$messages = new MessageBag(Message::ofUser('Review this code: function add($a, $b) { return $a + $b; }'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
