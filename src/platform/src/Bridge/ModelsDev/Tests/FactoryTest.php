<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
use Symfony\AI\Platform\Bridge\Gemini\Factory as GeminiFactory;
use Symfony\AI\Platform\Bridge\ModelsDev\Factory;
use Symfony\AI\Platform\Bridge\VertexAi\Factory as VertexAiFactory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Platform;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FactoryTest extends TestCase
{
    public function testCreateWithProviderThatHasApiBaseUrl()
    {
        $platform = Factory::createPlatform(
            provider: 'deepseek',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithExplicitBaseUrl()
    {
        $platform = Factory::createPlatform(
            provider: 'openai',
            apiKey: 'test-key',
            baseUrl: 'https://api.openai.com/v1',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithWellKnownNpmPackageProvider()
    {
        $platform = Factory::createPlatform(
            provider: 'openai',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateThrowsWhenNoBaseUrlAvailable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not have a known API base URL');

        Factory::createPlatform(
            provider: 'azure',
            apiKey: 'test-key',
        );
    }

    public function testCreateWithProviderHavingApiUrl()
    {
        $platform = Factory::createPlatform(
            provider: 'deepseek',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testProviderWithAnthropicBridgeRoutesAutomatically()
    {
        // If symfony/ai-anthropic-platform is installed, it should route automatically
        if (!class_exists(AnthropicFactory::class)) {
            $this->markTestSkipped('Anthropic bridge not installed');
        }

        $platform = Factory::createPlatform(
            provider: 'anthropic',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testProviderUsingAnthropicApiRoutesAutomatically()
    {
        // Providers like minimax use Anthropic's API format
        if (!class_exists(AnthropicFactory::class)) {
            $this->markTestSkipped('Anthropic bridge not installed');
        }

        $platform = Factory::createPlatform(
            provider: 'minimax',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testProviderWithGeminiBridgeRoutesAutomatically()
    {
        // If symfony/ai-gemini-platform is installed, it should route automatically
        if (!class_exists(GeminiFactory::class)) {
            $this->markTestSkipped('Gemini bridge not installed');
        }

        $platform = Factory::createPlatform(
            provider: 'google',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testProviderWithNonRoutableBridgeShowsHelpfulError()
    {
        // Vertex AI requires different parameters (location, projectId)
        // If the bridge is not installed, we get the "install it" error
        // If the bridge IS installed, we get the "different signature" error

        $this->expectException(InvalidArgumentException::class);

        if (class_exists(VertexAiFactory::class)) {
            $this->expectExceptionMessage('Provider "google-vertex" requires "symfony/ai-vertex-ai-platform" which has a different factory signature');
        } else {
            $this->expectExceptionMessage('Provider "google-vertex" requires a specialized bridge (@ai-sdk/google-vertex); install it with composer require "symfony/ai-vertex-ai-platform".');
        }

        Factory::createPlatform(
            provider: 'google-vertex',
            apiKey: 'test-key',
        );
    }

    public function testAutoDetectsCompletionsAndEmbeddingsSupport()
    {
        // OpenAI has both completions and embeddings models
        $platform = Factory::createPlatform(
            provider: 'openai',
            apiKey: 'test-key',
            baseUrl: 'https://api.openai.com/v1',
        );

        $this->assertInstanceOf(Platform::class, $platform);

        // DeepSeek also has both types
        $platform = Factory::createPlatform(
            provider: 'deepseek',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }
}
