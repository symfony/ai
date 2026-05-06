<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Perplexity\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = Factory::createPlatform(env('PERPLEXITY_API_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('What is the best French cheese?'));
$result = $platform->invoke('sonar', $messages);

echo $result->asText().\PHP_EOL;

// Multi-turn: feed the assistant's reply back into the bag and ask a follow-up.
$messages->add(Message::ofAssistant($result->asText()));
$messages->add(Message::ofUser('Which region of France produces it?'));
$result = $platform->invoke('sonar', $messages);

echo $result->asText().\PHP_EOL;
