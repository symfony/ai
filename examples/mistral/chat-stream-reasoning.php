<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('MISTRAL_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('What is 23 * 17?'),
);

// With `reasoning_effort: high`, Mistral returns the thinking trace as array-shaped content
// chunks, which the bridge maps to ThinkingDelta/ThinkingComplete before the answer TextDeltas.
$result = $platform->invoke('mistral-medium-2604', $messages, [
    'stream' => true,
    PlatformOptions::PROVIDER_OPTIONS => [
        'reasoning_effort' => 'high',
    ],
]);

$thinking = false;
foreach ($result->asStream() as $delta) {
    if ($delta instanceof ThinkingDelta) {
        if (!$thinking) {
            output()->writeln('<info><thinking></info>');
            $thinking = true;
        }
        output()->write('<fg=#999999>'.$delta->getThinking().'</>');
    }
    if ($delta instanceof ThinkingComplete) {
        output()->writeln(\PHP_EOL.'<info></thinking></info>');
        $thinking = false;
    }
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}
echo \PHP_EOL;
