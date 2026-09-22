<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\JobClient;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform('sk-test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $platform = Factory::createPlatform('sk-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('sk-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testTheBatchHandleCarriesTheNameTheProviderWasCreatedWith()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'file-abc']),
            new JsonMockResponse(['id' => 'batch_123', 'endpoint' => '/v1/responses', 'completion_window' => '24h', 'status' => 'validating']),
        ]);

        $handle = Factory::createProvider('sk-test-api-key', $httpClient, name: 'openai-eu')
            ->invoke('gpt-4o-mini', ['first' => new MessageBag(Message::ofUser('What is the capital of France?'))], ['batch' => true])
            ->asJob();

        $this->assertSame('batch_123', $handle->getId());
        $this->assertSame('openai-eu', $handle->getProvider());
    }

    public function testTheJobClientResolvesTheHandlesThisBridgeHandsOut()
    {
        $handle = new JobHandle('batch_123', ['kind' => JobClient::KIND], 'openai');

        $this->assertTrue(Factory::createJobClient('sk-test-api-key')->supports($handle));
    }
}
