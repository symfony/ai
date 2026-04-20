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
use Symfony\AI\Platform\Bridge\VertexAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), env('GOOGLE_CLOUD_VERTEX_API_KEY'));

$agent = new Agent($platform, 'gemini-2.5-pro');

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
