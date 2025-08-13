<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory;
use Symfony\AI\Platform\Bridge\Mistral\TokenUsageExtractor;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TokenUsage\TokenUsageOutputProcessor;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('MISTRAL_API_KEY'), http_client());
$model = new Mistral(Mistral::MISTRAL_SMALL, [
    'temperature' => 0.5, // default options for the model
]);
$agent = new Agent(
    $platform,
    $model,
    outputProcessors: [new TokenUsageOutputProcessor(new TokenUsageExtractor())],
    logger: logger()
);

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);

$result = $agent->call($messages, [
    'max_tokens' => 500,
]);

if (null === $tokenUsage = $result->getTokenUsage()) {
    throw new RuntimeException('Token usage is not available.');
}

echo 'Utilized Tokens: '.$tokenUsage->total.\PHP_EOL;
echo '-- Prompt Tokens: '.$tokenUsage->prompt.\PHP_EOL;
echo '-- Completion Tokens: '.$tokenUsage->completion.\PHP_EOL;
