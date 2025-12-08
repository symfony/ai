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

// Helper function to estimate tokens
function estimateTokens(MessageBag $messageBag): int
{
    $text = '';
    foreach ($messageBag->getMessages() as $message) {
        $content = $message->getContent();
        if (\is_string($content)) {
            $text .= $content;
        }
    }

    // Rough estimate: 1 token ≈ 4 characters
    return (int) (\strlen($text) / 4);
}

// Create platform
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Cost-optimized router: use cheaper models for simple queries
$router = new SimpleRouter(
    function ($input, $ctx) {
        $tokenCount = estimateTokens($input->getMessageBag());

        if ($tokenCount < 100) {
            return new RoutingResult(
                'gpt-4o-mini',
                reason: "Low cost for short query ({$tokenCount} tokens)"
            );
        }

        if ($tokenCount < 500) {
            return new RoutingResult(
                'gpt-4o-mini',
                reason: "Balanced cost for medium query ({$tokenCount} tokens)"
            );
        }

        return new RoutingResult(
            'gpt-4o',
            reason: "Full model for complex query ({$tokenCount} tokens)"
        );
    }
);

// Create agent with router
$agent = new Agent(
    platform: $platform,
    model: 'gpt-4o', // Default model
    inputProcessors: [
        new ModelRouterInputProcessor($router),
    ],
);

echo "Example 5: Cost-Optimized Routing\n";
echo "==================================\n\n";

// Test 1: Short query
echo "Test 1: Short query (< 100 tokens)\n";
$result = $agent->call(new MessageBag(
    Message::ofUser('What is 2 + 2?')
));
echo 'Response: '.$result->asText()."\n\n";

// Test 2: Medium query
echo "Test 2: Medium query (100-500 tokens)\n";
$result = $agent->call(new MessageBag(
    Message::ofUser('Explain the concept of object-oriented programming and give me a few examples.')
));
echo 'Response: '.$result->asText()."\n\n";

// Test 3: Long query
echo "Test 3: Long query (> 500 tokens)\n";
$longText = str_repeat('This is a longer text that requires more processing. ', 50);
$result = $agent->call(new MessageBag(
    Message::ofUser("Analyze this text and provide insights: {$longText}")
));
echo 'Response: '.$result->asText()."\n";
