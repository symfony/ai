<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Fireworks\Factory;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FactoryTest extends TestCase
{
    public function testCreateProviderReturnsProvider()
    {
        $provider = Factory::createProvider('api-key');

        $this->assertInstanceOf(ProviderInterface::class, $provider);
        $this->assertSame('fireworks', $provider->getName());
    }

    public function testCreatePlatformReturnsPlatform()
    {
        $this->assertInstanceOf(Platform::class, Factory::createPlatform('api-key'));
    }

    public function testCreatePlatformUsesProvidedHttpClient()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer api-key', $options['normalized_headers']['authorization'][0]);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $platform = Factory::createPlatform('api-key', $httpClient);

        // Passing a fully defined model bypasses the dynamic catalog, so no gateway request is issued.
        $result = $platform->invoke(
            new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]),
            new MessageBag(Message::ofUser('Hi')),
        );

        $this->assertSame('Hello', $result->asText());
        $this->assertTrue($requestMade);
    }
}
