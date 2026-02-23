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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ShippingOrder\ShippingOrder;

require_once dirname(__DIR__).'/bootstrap.php';

/*
 * This example demonstrates that the ToolCallArgumentResolver supports nested
 * objects, backed enums, datetime, arrays of objects, and type resolution via PHP docblocks.
 */

#[AsTool('create_shipping_order', 'Create a shipping order with items to ship to an address', method: 'create')]
final class ShippingTool
{
    /**
     * @param ShippingOrder $order The shipping order to create
     */
    public function create(ShippingOrder $order): string
    {
        dump($order);

        return 'Shipping order created.';
    }
}

$platforms = [
    'claude-sonnet-4-5-20250929' => AnthropicPlatformFactory::create(env('ANTHROPIC_API_KEY'), httpClient: http_client()),
    'gpt-5-mini' => OpenAiPlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client()),
    'gemini-2.5-flash' => GeminiPlatformFactory::create(env('GEMINI_API_KEY'), httpClient: http_client()),
];

foreach ($platforms as $model => $platform) {
    $shippingTool = new ShippingTool();
    $toolbox = new Toolbox(tools: [$shippingTool]);
    $processor = new AgentProcessor(toolbox: $toolbox);
    $agent = new Agent($platform, $model, [$processor], [$processor]);

    echo "\n=== Testing with model: $model ===\n\n";

    $messages = new MessageBag(
        Message::forSystem('You are a shipping assistant. Help users create shipping orders.'),
        Message::ofUser('Create an express shipping order for John Doe at 123 Main St, Springfield, 62701 to be delivered by 2026-03-15 with 2 items: 3x Widget ($9.99 each) and 1x Gadget ($24.99)'),
    );

    $result = $agent->call($messages);

    echo $result->getContent().\PHP_EOL;
}
