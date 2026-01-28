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
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// Create platform
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Router that checks model capabilities and routes accordingly
$router = new SimpleRouter(
    function ($input, $ctx) {
        $catalog = $ctx->getCatalog();
        $currentModel = $input->getModel();

        // If no catalog available, keep current model
        if ($catalog === null) {
            return null;
        }

        // Check if input contains image
        if ($input->getMessageBag()->containsImage()) {
            try {
                $model = $catalog->getModel($currentModel);

                // Check if current model supports vision
                if (!$model->supports(Capability::INPUT_IMAGE)) {
                    // Find a model that supports vision
                    $visionModels = $ctx->findModelsWithCapabilities(Capability::INPUT_IMAGE);

                    if (empty($visionModels)) {
                        throw new \RuntimeException('No vision-capable model found');
                    }

                    return new RoutingResult(
                        $visionModels[0],
                        reason: "Current model '{$currentModel}' doesn't support vision - switching to '{$visionModels[0]}'"
                    );
                }
            } catch (\Exception $e) {
                // Model not found in catalog, try to find a vision model
                $visionModels = $ctx->findModelsWithCapabilities(Capability::INPUT_IMAGE);
                if (!empty($visionModels)) {
                    return new RoutingResult(
                        $visionModels[0],
                        reason: "Switching to vision-capable model '{$visionModels[0]}'"
                    );
                }
            }
        }

        return null; // Keep current model
    }
);

// Create agent with router
$agent = new Agent(
    platform: $platform,
    model: 'gpt-4o-mini', // Default model (supports vision)
    inputProcessors: [
        new ModelRouterInputProcessor($router),
    ],
);

echo "Example 6: Capability-Based Routing\n";
echo "====================================\n\n";

// Test 1: Text query
echo "Test 1: Text query (no special capabilities needed)\n";
$result = $agent->call(new MessageBag(
    Message::ofUser('What is artificial intelligence?')
));
echo 'Response: '.$result->asText()."\n\n";

// Test 2: Image query
echo "Test 2: Image query (requires vision capability)\n";
$imagePath = realpath(__DIR__.'/../../fixtures/assets/image-sample.png');
if (!file_exists($imagePath)) {
    echo "Image file not found: {$imagePath}\n";
    exit(1);
}

$result = $agent->call(new MessageBag(
    Message::ofUser('What do you see in this image?')->withImage($imagePath)
));
echo 'Response: '.$result->asText()."\n";
