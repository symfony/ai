<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\AmazeeAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('AMAZEEAI_LLM_API_URL'), env('AMAZEEAI_LLM_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $platform->invoke('claude-3-5-sonnet', $messages);

echo $result->asText().\PHP_EOL;

// Multi-turn: feed the assistant's reply back into the bag and ask a follow-up.
$messages->add(Message::ofAssistant($result->asText()));
$messages->add(Message::ofUser('And which versions are LTS?'));
$result = $platform->invoke('claude-3-5-sonnet', $messages);

echo $result->asText().\PHP_EOL;
