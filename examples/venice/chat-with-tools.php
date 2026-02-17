<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Venice\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCallResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('VENICE_API_KEY'), httpClient: http_client());

$tools = [[
    'type' => 'function',
    'function' => [
        'name' => 'get_weather',
        'description' => 'Get the current weather for a city',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'City name'],
            ],
            'required' => ['city'],
        ],
    ],
]];

$deferred = $platform->invoke(
    'venice-uncensored',
    new MessageBag(Message::ofUser('What is the weather in Paris right now?')),
    ['tools' => $tools],
);

$result = $deferred->getResult();

if ($result instanceof ToolCallResult) {
    foreach ($result->getContent() as $call) {
        echo sprintf("Model wants to call %s(%s)\n", $call->getName(), json_encode($call->getArguments()));
    }
} else {
    echo $deferred->asText().\PHP_EOL;
}
