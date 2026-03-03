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
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\ModelsDev\PlatformFactory;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory as VertexAiPlatformFactory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Platform;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PlatformFactoryTest extends TestCase
{
    public function testCreateWithProviderThatHasApiBaseUrl()
    {
        $platform = PlatformFactory::create(
            provider: 'deepseek',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithExplicitBaseUrl()
    {
        $platform = PlatformFactory::create(
            provider: 'openai',
            apiKey: 'test-key',
            baseUrl: 'https://api.openai.com/v1',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateWithWellKnownNpmPackageProvider()
    {
        $platform = PlatformFactory::create(
            provider: 'openai',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateThrowsWhenNoBaseUrlAvailable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not have a known API base URL');

        PlatformFactory::create(
            provider: 'azure',
            apiKey: 'test-key',
        );
    }

    public function testCreateWithProviderHavingApiUrl()
    {
        $platform = PlatformFactory::create(
            provider: 'deepseek',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testProviderWithAnthropicBridgeRoutesAutomatically()
    {
        // If symfony/ai-anthropic-platform is installed, it should route automatically
        if (!class_exists(AnthropicPlatformFactory::class)) {
            $this->markTestSkipped('Anthropic bridge not installed');
        }

        $platform = PlatformFactory::create(
            provider: 'anthropic',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testProviderUsingAnthropicApiRoutesAutomatically()
    {
        // Providers like minimax use Anthropic's API format
        if (!class_exists(AnthropicPlatformFactory::class)) {
            $this->markTestSkipped('Anthropic bridge not installed');
        }

        $platform = PlatformFactory::create(
            provider: 'minimax',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testProviderWithGeminiBridgeRoutesAutomatically()
    {
        // If symfony/ai-gemini-platform is installed, it should route automatically
        if (!class_exists(GeminiPlatformFactory::class)) {
            $this->markTestSkipped('Gemini bridge not installed');
        }

        $platform = PlatformFactory::create(
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

        if (class_exists(VertexAiPlatformFactory::class)) {
            $this->expectExceptionMessage('Provider "google-vertex" requires "symfony/ai-vertex-ai-platform" which has a different factory signature');
        } else {
            $this->expectExceptionMessage('Provider "google-vertex" requires a specialized bridge (@ai-sdk/google-vertex); install it with composer require "symfony/ai-vertex-ai-platform".');
        }

        PlatformFactory::create(
            provider: 'google-vertex',
            apiKey: 'test-key',
        );
    }

    public function testAutoDetectsCompletionsAndEmbeddingsSupport()
    {
        // OpenAI has both completions and embeddings models
        $platform = PlatformFactory::create(
            provider: 'openai',
            apiKey: 'test-key',
            baseUrl: 'https://api.openai.com/v1',
        );

        $this->assertInstanceOf(Platform::class, $platform);

        // DeepSeek also has both types
        $platform = PlatformFactory::create(
            provider: 'deepseek',
            apiKey: 'test-key',
        );

        $this->assertInstanceOf(Platform::class, $platform);
    }
}
