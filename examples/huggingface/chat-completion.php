<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());

$messages = new MessageBag(Message::ofUser('Hello, how are you doing today?'));
$result = $platform->invoke('HuggingFaceH4/zephyr-7b-beta', $messages, [
    'task' => Task::CHAT_COMPLETION,
]);

echo $result->asText().\PHP_EOL;
