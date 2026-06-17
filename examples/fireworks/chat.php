<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Fireworks\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('FIREWORKS_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a concise, helpful assistant.'),
    Message::ofUser('What is the capital of France, in one sentence?'),
);
$result = $platform->invoke('accounts/fireworks/models/kimi-k2p6', $messages);

echo $result->asText().\PHP_EOL;
