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
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\TokenOutputProcessor;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Metadata\TokenUsage;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());
$model = new Gemini(Gemini::GEMINI_2_FLASH);

$agent = new Agent($platform, $model, outputProcessors: [new TokenOutputProcessor()], logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $agent->call($messages);

$metadata = $result->getMetadata();
$tokenUsage = $metadata->get('token_usage');

assert($tokenUsage instanceof TokenUsage);

echo 'Utilized Tokens: '.$tokenUsage->totalTokens.\PHP_EOL;
echo '-- Prompt Tokens: '.$tokenUsage->promptTokens.\PHP_EOL;
echo '-- Completion Tokens: '.$tokenUsage->completionTokens.\PHP_EOL;
