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
use Symfony\AI\Agent\Router\Result\RoutingResult;
use Symfony\AI\Agent\Router\SimpleRouter;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// Create platform
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Router that uses vision model for images, default for text
$router = new SimpleRouter(
    fn ($input, $ctx) => $input->getMessageBag()->containsImage()
        ? new RoutingResult('gpt-4o', reason: 'Vision model for images')
        : new RoutingResult($ctx->getDefaultModel(), reason: 'Default model for text')
);

// Create agent with router
$agent = new Agent(
    platform: $platform,
    model: 'gpt-4o-mini', // Default model
    inputProcessors: [
        new ModelRouterInputProcessor($router),
    ],
);

echo "Example 2: Vision with Fallback\n";
echo "================================\n\n";

// Test 1: Text query
echo "Test 1: Text query → gpt-4o-mini (default)\n";
$result = $agent->call(new MessageBag(
    Message::ofUser('Explain quantum computing in simple terms')
));
echo 'Response: '.$result->asText()."\n\n";

// Test 2: Image query
echo "Test 2: Image query → gpt-4o (vision)\n";
$imagePath = realpath(__DIR__.'/../../fixtures/assets/image-sample.png');
if (!file_exists($imagePath)) {
    echo "Image file not found: {$imagePath}\n";
    exit(1);
}

$result = $agent->call(new MessageBag(
    Message::ofUser('Describe this image')->withImage($imagePath)
));
echo 'Response: '.$result->asText()."\n";
