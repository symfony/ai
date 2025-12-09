<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Scaleway\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('SCALEWAY_SECRET_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are concise and respond with two short sentences.'),
    Message::ofUser('What is new in Symfony AI?'),
);

// gpt-oss-120b now goes through Scaleway Responses API under the hood.
$result = $platform->invoke('gpt-oss-120b', $messages, [
    'reasoning' => ['effort' => 'medium'],
    'max_output_tokens' => 200,
]);

echo $result->asText().\PHP_EOL;
