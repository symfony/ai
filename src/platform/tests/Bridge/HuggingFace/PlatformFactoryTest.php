<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class PlatformFactoryTest extends TestCase
{
    #[TestDox('Creates Platform with default provider and auto-generated components')]
    public function testCreateWithDefaults()
    {
        $platform = PlatformFactory::create('test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestDox('Creates Platform with custom provider')]
    public function testCreateWithCustomProvider()
    {
        $platform = PlatformFactory::create('test-api-key', Provider::COHERE);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestDox('Handles EventSourceHttpClient correctly')]
    public function testCreateWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = PlatformFactory::create('test-api-key', Provider::HF_INFERENCE, $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestDox('Creates Platform successfully with all supported providers')]
    #[TestWith([Provider::CEREBRAS])]
    #[TestWith([Provider::COHERE])]
    #[TestWith([Provider::FAL_AI])]
    #[TestWith([Provider::FEATHERLESS_AI])]
    #[TestWith([Provider::FIREWORKS])]
    #[TestWith([Provider::GROQ])]
    #[TestWith([Provider::HF_INFERENCE])]
    #[TestWith([Provider::HYPERBOLIC])]
    #[TestWith([Provider::NEBIUS])]
    #[TestWith([Provider::NOVITA])]
    #[TestWith([Provider::NSCALE])]
    #[TestWith([Provider::PUBLIC_AI])]
    #[TestWith([Provider::REPLICATE])]
    #[TestWith([Provider::SAMBA_NOVA])]
    #[TestWith([Provider::SCALEWAY])]
    #[TestWith([Provider::TOGETHER])]
    #[TestWith([Provider::WAVE_SPEED_AI])]
    #[TestWith([Provider::Z_AI])]
    public function testCreateWithDifferentProviders(string $provider)
    {
        $platform = PlatformFactory::create('test-api-key', $provider);
        $this->assertInstanceOf(Platform::class, $platform);
    }
}
