<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatform;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$rateLimiter = new RateLimiterFactory([
    'policy' => 'sliding_window',
    'id' => 'failover',
    'interval' => '3 seconds',
    'limit' => 1,
], new InMemoryStorage());

$platform = new FailoverPlatform([
    ['platform' => OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), http_client()), 'model' => 'gpt-4o'],
    ['platform' => AnthropicPlatformFactory::create(env('ANTHROPIC_API_KEY'), http_client()), 'model' => 'claude-sonnet-4-20250514'],
], $rateLimiter);

// OpenAI receives 'gpt-4o', if it fails, Anthropic receives 'claude-sonnet-4-20250514'
$result = $platform->invoke('gpt-4o', new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('What is the capital of France?'),
));
