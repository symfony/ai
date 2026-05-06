<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\VertexAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = Factory::createPlatform(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client());

$messages = new MessageBag(
    Message::forSystem('You are an expert assistant in geography.'),
    Message::ofUser('Where is Mount Fuji?'),
);
$result = $platform->invoke('gemini-2.5-flash', $messages);

echo $result->asText().\PHP_EOL;

// Multi-turn: feed the assistant's reply back into the bag and ask a follow-up.
$messages->add(Message::ofAssistant($result->asText()));
$messages->add(Message::ofUser('And what is its elevation?'));
$result = $platform->invoke('gemini-2.5-flash', $messages);

echo $result->asText().\PHP_EOL;
