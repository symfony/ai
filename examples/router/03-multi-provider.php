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
use Symfony\AI\Agent\InputProcessor\ModelRouterInputProcessor;
use Symfony\AI\Agent\Router\ChainRouter;
use Symfony\AI\Agent\Router\Result\RoutingResult;
use Symfony\AI\Agent\Router\SimpleRouter;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// For this example, we'll use OpenAI platform, but show routing to different models
// In a real scenario, you might have multiple platform instances
$platform = OpenAiFactory::create(env('OPENAI_API_KEY'), http_client());

// Create chain router that tries multiple strategies
$router = new ChainRouter([
    // Strategy 1: Try gpt-4o for images
    new SimpleRouter(
        fn ($input) =>
            $input->getMessageBag()->containsImage()
                ? new RoutingResult('gpt-4o', reason: 'OpenAI GPT-4o for vision')
                : null
    ),

    // Strategy 2: Fallback to gpt-4o-mini for simple text
    new SimpleRouter(
        fn ($input) =>
            !$input->getMessageBag()->containsImage()
                ? new RoutingResult('gpt-4o-mini', reason: 'OpenAI GPT-4o-mini for text')
                : null
    ),

    // Strategy 3: Default fallback
    new SimpleRouter(
        fn ($input, $ctx) => new RoutingResult($ctx->getDefaultModel(), reason: 'Default')
    ),
]);

// Create agent with chain router
$agent = new Agent(
    platform: $platform,
    model: 'gpt-4o-mini', // Default model
    inputProcessors: [
        new ModelRouterInputProcessor($router),
    ],
);

echo "Example 3: Multi-Provider Routing\n";
echo "==================================\n\n";

// Test 1: Simple text
echo "Test 1: Simple text → gpt-4o-mini\n";
$result = $agent->call(new MessageBag(
    Message::ofUser('What is 2 + 2?')
));
echo 'Response: '.$result->asText()."\n\n";

// Test 2: Image
echo "Test 2: Image → gpt-4o\n";
$imagePath = realpath(__DIR__.'/../../fixtures/assets/image-sample.png');
if (!file_exists($imagePath)) {
    echo "Image file not found: {$imagePath}\n";
    exit(1);
}

$result = $agent->call(new MessageBag(
    Message::ofUser('What is in this image?')->withImage($imagePath)
));
echo 'Response: '.$result->asText()."\n";
