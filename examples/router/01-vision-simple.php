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

// Create simple vision router: if message contains image → use gpt-4-vision
$visionRouter = new SimpleRouter(
    fn ($input, $ctx) =>
        $input->getMessageBag()->containsImage()
            ? new RoutingResult('gpt-4o', reason: 'Image detected')
            : null
);

// Create agent with router
$agent = new Agent(
    platform: $platform,
    model: 'gpt-4o-mini', // Default model
    inputProcessors: [
        new ModelRouterInputProcessor($visionRouter),
    ],
);

echo "Example 1: Simple Vision Routing\n";
echo "=================================\n\n";

// Test 1: Text only - should use default model (gpt-4o-mini)
echo "Test 1: Text only message\n";
$result = $agent->call(new MessageBag(
    Message::ofUser('What is PHP?')
));
echo 'Response: '.$result->asText()."\n\n";

// Test 2: With image - should automatically route to gpt-4o
echo "Test 2: Message with image\n";
$imagePath = realpath(__DIR__.'/../../fixtures/assets/image-sample.png');
if (!file_exists($imagePath)) {
    echo "Image file not found: {$imagePath}\n";
    exit(1);
}

$result = $agent->call(new MessageBag(
    Message::ofUser('What is in this image?')->withImage($imagePath)
));
echo 'Response: '.$result->asText()."\n";
