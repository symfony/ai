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
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryComplete;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\Component\Console\Formatter\OutputFormatter;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('Tell the user what you are about to do before you run code.'),
    Message::ofUser('What is the smallest positive integer whose factorial has more than 1000 trailing zeros? Verify it with Python, then answer with just the number.'),
);

// A stream carries three kinds of text: the reasoning summary, the narration of the next step
// while a tool runs ("commentary"), and the answer - each on a channel of its own.
$result = $platform->invoke('gpt-5.5', $messages, [
    'reasoning' => ['effort' => 'high', 'summary' => 'auto'],
    'tools' => [
        ['type' => 'code_interpreter', 'container' => ['type' => 'auto']],
    ],
    'stream' => true,
]);

foreach ($result->asStream() as $delta) {
    if ($delta instanceof ThinkingStart) {
        output()->writeln('<info><thinking></info>');
    }
    if ($delta instanceof ThinkingDelta) {
        output()->write('<fg=#999999>'.OutputFormatter::escape($delta->getThinking()).'</>');
    }
    if ($delta instanceof ThinkingComplete) {
        output()->writeln(\PHP_EOL.'<info></thinking></info>');
    }
    if ($delta instanceof CommentaryStart) {
        output()->writeln('<info><commentary></info>');
    }
    if ($delta instanceof CommentaryDelta) {
        output()->write('<fg=#999999>'.OutputFormatter::escape($delta->getCommentary()).'</>');
    }
    if ($delta instanceof CommentaryComplete) {
        output()->writeln(\PHP_EOL.'<info></commentary></info>');
    }
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}
echo \PHP_EOL;
