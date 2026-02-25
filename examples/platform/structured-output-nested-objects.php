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
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ShippingOrder\ShippingOrder;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

/*
 * This example demonstrates structured output with nested objects, backed enums,
 * datetime, and arrays of objects resolved via PHP docblocks.
 */

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$platforms = [
    'claude-sonnet-4-5-20250929' => AnthropicPlatformFactory::create(env('ANTHROPIC_API_KEY'), httpClient: http_client(), eventDispatcher: $dispatcher),
    'gpt-5-mini' => OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher),
    'gemini-2.5-flash' => GeminiPlatformFactory::create(env('GEMINI_API_KEY'), httpClient: http_client(), eventDispatcher: $dispatcher),
];

foreach ($platforms as $model => $platform) {
    echo "\n=== Testing with model: $model ===\n\n";

    $messages = new MessageBag(
        Message::forSystem('You are a shipping assistant. Create shipping orders based on user requests.'),
        Message::ofUser('Create an express shipping order for John Doe at 123 Main St, Springfield, 62701 to be delivered by 2026-03-15 with 2 items: 3x Widget ($9.99 each) and 1x Gadget ($24.99)'),
    );

    $result = $platform->invoke($model, $messages, ['response_format' => ShippingOrder::class]);

    $order = $result->asObject();
    assert($order instanceof ShippingOrder);
    dump($order);
}
