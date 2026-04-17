<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
use Symfony\AI\Platform\Bridge\Gemini\Factory as GeminiFactory;
use Symfony\AI\Platform\Bridge\ModelsDev\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// The models.dev bridge automatically detects and routes to specialized bridges
// when they're installed. All providers use the same interface!

$messages = new MessageBag(
    Message::ofUser('Say "Hello from [provider name]" in one sentence.'),
);

// Example 1: OpenAI-compatible provider (works immediately)
echo "1. DeepSeek (OpenAI-compatible):\n";
$platform = Factory::createPlatform('deepseek', env('DEEPSEEK_API_KEY'), httpClient: http_client());
$result = $platform->invoke('deepseek-chat', $messages);
echo $result->asText()."\n\n";

// Example 2: Anthropic provider (auto-routes to Anthropic bridge if installed)
if (class_exists(AnthropicFactory::class)) {
    echo "2. Anthropic (auto-routed to Anthropic bridge):\n";
    $platform = Factory::createPlatform('anthropic', env('ANTHROPIC_API_KEY'), httpClient: http_client());
    $result = $platform->invoke('claude-haiku-4-5', $messages);
    echo $result->asText()."\n\n";
} else {
    echo "2. Anthropic: Bridge not installed (run: composer require symfony/ai-anthropic-platform)\n\n";
}

// Example 3: Google Gemini (auto-routes to Gemini bridge if installed)
if (class_exists(GeminiFactory::class)) {
    echo "3. Google Gemini (auto-routed to Gemini bridge):\n";
    $platform = Factory::createPlatform('google', env('GEMINI_API_KEY'), httpClient: http_client());
    $result = $platform->invoke('gemini-2.5-flash', $messages);
    echo $result->asText()."\n\n";
} else {
    echo "3. Google Gemini: Bridge not installed (run: composer require symfony/ai-gemini-platform)\n\n";
}
