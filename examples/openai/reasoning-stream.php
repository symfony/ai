<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('What is 25 * 17?'),
);

$result = $platform->invoke('o3-mini', $messages, [
    'stream' => true,
    'reasoning' => ['summary' => 'auto'],
]);

foreach ($result->asStream() as $delta) {
    if ($delta instanceof ThinkingDelta) {
        echo '[Thinking] '.$delta->getThinking().\PHP_EOL;
    }
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}
echo \PHP_EOL;
