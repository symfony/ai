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
use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ProviderRegistryTest extends TestCase
{
    public function testGetApiBaseUrl()
    {
        $registry = new ProviderRegistry();

        $this->assertSame('https://api.deepseek.com', $registry->getApiBaseUrl('deepseek'));
    }

    public function testGetApiBaseUrlReturnsNullForProvidersWithoutApi()
    {
        $registry = new ProviderRegistry();

        $this->assertNull($registry->getApiBaseUrl('openai'));
    }

    public function testGetProviderName()
    {
        $registry = new ProviderRegistry();

        $this->assertSame('Groq', $registry->getProviderName('groq'));
    }

    public function testHasProvider()
    {
        $registry = new ProviderRegistry();

        $this->assertTrue($registry->has('openai'));
        $this->assertTrue($registry->has('groq'));
        $this->assertFalse($registry->has('nonexistent'));
    }

    public function testGetProviderIds()
    {
        $registry = new ProviderRegistry();

        $ids = $registry->getProviderIds();

        $this->assertNotEmpty($ids);

        // OpenAI-compatible providers should be available
        $this->assertContains('openai', $ids);
        $this->assertContains('groq', $ids);
        $this->assertContains('mistral', $ids);
        $this->assertContains('deepseek', $ids);

        // Providers with specialized bridges are also listed (routing handled by PlatformFactory)
        $this->assertContains('anthropic', $ids);
        $this->assertContains('google', $ids);
        $this->assertContains('minimax', $ids);
    }

    public function testUnknownProviderThrowsException()
    {
        $registry = new ProviderRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider "nonexistent" not found in registry.');

        $registry->getApiBaseUrl('nonexistent');
    }

    public function testProvidersWithSpecializedBridgesAreAvailable()
    {
        $registry = new ProviderRegistry();

        // Providers with specialized bridges should be listed in the registry
        $this->assertTrue($registry->has('anthropic'));
        $this->assertTrue($registry->has('google'));
        $this->assertTrue($registry->has('amazon-bedrock'));
        $this->assertTrue($registry->has('google-vertex'));

        // Providers using Anthropic's API (via @ai-sdk/anthropic) are also available
        $this->assertTrue($registry->has('minimax'));
        $this->assertTrue($registry->has('kimi-for-coding'));
        $this->assertTrue($registry->has('zenmux'));
    }

    public function testCustomApiJsonPath()
    {
        $path = sys_get_temp_dir().'/models-dev-registry-test-'.uniqid().'.json';
        file_put_contents($path, json_encode([
            'my-provider' => [
                'id' => 'my-provider',
                'name' => 'My Provider',
                'api' => 'https://api.my-provider.com/v1',
                'models' => [],
            ],
        ], \JSON_THROW_ON_ERROR));

        try {
            $registry = new ProviderRegistry($path);

            $this->assertTrue($registry->has('my-provider'));
            $this->assertSame('My Provider', $registry->getProviderName('my-provider'));
            $this->assertSame('https://api.my-provider.com/v1', $registry->getApiBaseUrl('my-provider'));
        } finally {
            unlink($path);
        }
    }
}
