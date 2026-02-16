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
use Symfony\AI\Platform\Bridge\ModelsDev\BridgeResolver;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BridgeResolverTest extends TestCase
{
    public function testRequiresSpecializedBridge()
    {
        $this->assertTrue(BridgeResolver::requiresSpecializedBridge('@ai-sdk/anthropic'));
        $this->assertTrue(BridgeResolver::requiresSpecializedBridge('@ai-sdk/google'));
        $this->assertFalse(BridgeResolver::requiresSpecializedBridge('@ai-sdk/openai'));
        $this->assertFalse(BridgeResolver::requiresSpecializedBridge('@ai-sdk/openai-compatible'));
    }

    public function testGetBridgeFactory()
    {
        $this->assertSame(AnthropicPlatformFactory::class, BridgeResolver::getBridgeFactory('@ai-sdk/anthropic'));
        $this->assertSame(GeminiPlatformFactory::class, BridgeResolver::getBridgeFactory('@ai-sdk/google'));
        $this->assertNull(BridgeResolver::getBridgeFactory('@ai-sdk/openai'));
    }

    public function testGetComposerPackage()
    {
        $this->assertSame('symfony/ai-anthropic-platform', BridgeResolver::getComposerPackage('@ai-sdk/anthropic'));
        $this->assertSame('symfony/ai-gemini-platform', BridgeResolver::getComposerPackage('@ai-sdk/google'));
        $this->assertNull(BridgeResolver::getComposerPackage('@ai-sdk/openai'));
    }

    public function testIsRoutable()
    {
        // Bridges with compatible factory signatures are routable
        $this->assertTrue(BridgeResolver::isRoutable('@ai-sdk/anthropic'));
        $this->assertTrue(BridgeResolver::isRoutable('@ai-sdk/google'));

        // Bridges requiring special parameters are not routable
        $this->assertFalse(BridgeResolver::isRoutable('@ai-sdk/google-vertex'));
        $this->assertFalse(BridgeResolver::isRoutable('@ai-sdk/amazon-bedrock'));

        // Unknown packages are not routable
        $this->assertFalse(BridgeResolver::isRoutable('@ai-sdk/unknown'));
    }
}
