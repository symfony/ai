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
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$agent = new Agent($platform, 'gemini-3.1-pro-preview');

$messages = new MessageBag(
    Message::ofUser('What is the closest hotel to me?'),
);

$result = $agent->call($messages, [
    'server_tools' => ['google_maps' => true],
    'tool_config' => [
        'retrieval_config' => [
            'lat_lng' => ['latitude' => 60.16653, 'longitude' => 24.93061],
        ],
    ],
]);

echo $result->getContent().\PHP_EOL;
