<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: optional_env('AWS_BEARER_TOKEN_BEDROCK'),
    region: env('AWS_DEFAULT_REGION'),
    httpClient: http_client(),
);

$messages = new MessageBag(
    Message::forSystem('You are a thoughtful philosopher.'),
    Message::ofUser('What is the purpose of an ant?'),
);
$result = $platform->invoke('google.gemma-4-31b', $messages, ['stream' => true]);

foreach ($result->asStream() as $delta) {
    if ($delta instanceof ThinkingStart) {
        output()->writeln('<info><thinking></info>');
    }
    if ($delta instanceof ThinkingDelta) {
        output()->write('<fg=#999999>'.$delta->getThinking().'</>');
    }
    if ($delta instanceof ThinkingComplete) {
        output()->writeln('<info></thinking></info>');
    }
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}
echo \PHP_EOL;
