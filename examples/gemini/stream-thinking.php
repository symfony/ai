<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant that reasons step by step.'),
    Message::ofUser('If a train travels 60 km in 45 minutes, what is its average speed in km/h?'),
);

// Enabling thought summaries makes Gemini stream "thought" parts before the answer; the streaming
// converter frames them with ThinkingStart / ThinkingComplete boundaries around the ThinkingDelta
// instances and turns the answer into TextDelta instances.
$result = $platform->invoke('gemini-2.5-flash', $messages, [
    'stream' => true,
    'thinkingConfig' => [
        'includeThoughts' => true,
    ],
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
