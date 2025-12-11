<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// Set title AND uri to track the calls in the OpenRouter Activity feed
$client = http_client()->withOptions([
    'headers' => [
        'HTTP-Referer' => 'https://ai.symfony.com/',
        'X-Title' => 'My Special Symfony AI App',
    ],
]);

$platform = PlatformFactory::create(env('OPENROUTER_KEY'), $client);

$messages = new MessageBag(
    Message::forSystem('Output two sentences related to the user topic.'),
    Message::ofUser('Chess'),
);

$result = $platform->invoke('google/gemini-2.5-flash-lite', $messages);

echo $result->asText().\PHP_EOL;
