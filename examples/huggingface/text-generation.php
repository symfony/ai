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
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());

$messages = new MessageBag(Message::ofUser('The quick brown fox jumps over the lazy'));
$result = $platform->invoke('meta-llama/Llama-3.2-3B-Instruct', $messages, [
    'task' => Task::CHAT_COMPLETION,
    'provider' => Provider::HYPERBOLIC,
]);

echo $result->asText().\PHP_EOL;
