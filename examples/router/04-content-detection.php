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

// Router that handles multiple content types
$router = new SimpleRouter(
    fn ($input, $ctx) => match (true) {
        $input->getMessageBag()->containsImage() => new RoutingResult(
            'gpt-4o',
            reason: 'Image detected - using vision model'
        ),
        $input->getMessageBag()->containsAudio() => new RoutingResult(
            'whisper-1',
            reason: 'Audio detected - using speech-to-text model'
        ),
        $input->getMessageBag()->containsPdf() => new RoutingResult(
            'gpt-4o',
            reason: 'PDF detected - using advanced model'
        ),
        default => new RoutingResult(
            $ctx->getDefaultModel(),
            reason: 'Text only - using default model'
        ),
    }
);

// Create agent with router
$agent = new Agent(
    platform: $platform,
    model: 'gpt-4o-mini', // Default model
    inputProcessors: [
        new ModelRouterInputProcessor($router),
    ],
);

echo "Example 4: Content Type Detection\n";
echo "==================================\n\n";

// Test 1: Plain text
echo "Test 1: Plain text\n";
$result = $agent->call(new MessageBag(
    Message::ofUser('What is machine learning?')
));
echo 'Response: '.$result->asText()."\n\n";

// Test 2: Image
echo "Test 2: Image content\n";
$imagePath = realpath(__DIR__.'/../../fixtures/assets/image-sample.png');
if (!file_exists($imagePath)) {
    echo "Image file not found: {$imagePath}\n";
    exit(1);
}

$result = $agent->call(new MessageBag(
    Message::ofUser('Analyze this image')->withImage($imagePath)
));
echo 'Response: '.$result->asText()."\n\n";

// Test 3: PDF (if available)
$pdfPath = realpath(__DIR__.'/../../fixtures/assets/pdf-sample.pdf');
if (file_exists($pdfPath)) {
    echo "Test 3: PDF content\n";
    $result = $agent->call(new MessageBag(
        Message::ofUser('Summarize this PDF')->withPdf($pdfPath)
    ));
    echo 'Response: '.$result->asText()."\n";
} else {
    echo "Test 3: PDF content - skipped (PDF file not found)\n";
}
