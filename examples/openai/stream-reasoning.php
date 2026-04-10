<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('What is 25 * 17?'),
);

$result = $platform->invoke('o3-mini', $messages, [
    'stream' => true,
    'reasoning' => ['summary' => 'auto'],
]);

foreach ($result->asStream() as $delta) {
    if ($delta instanceof ThinkingStart) {
        output()->writeln('<info><thinking></info>');
    }
    if ($delta instanceof ThinkingDelta) {
        output()->write('<fg=#999999>'.$delta->getThinking().'</>');
    }
    if ($delta instanceof ThinkingComplete) {
        output()->writeln(\PHP_EOL.'<info></thinking></info>');
    }
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}
echo \PHP_EOL;
