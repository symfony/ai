<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Batch\JobClient;
use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatform()
    {
        $this->assertInstanceOf(Platform::class, Factory::createPlatform('mistral-key', new MockHttpClient()));
    }

    public function testTheBatchHandleCarriesTheNameTheProviderWasCreatedWith()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'file-abc']),
            new JsonMockResponse(['id' => 'batch_123', 'endpoint' => '/v1/chat/completions', 'status' => 'QUEUED']),
        ]);

        $handle = Factory::createProvider('mistral-key', $httpClient, name: 'mistral-eu')
            ->invoke('mistral-large-latest', ['first' => new MessageBag(Message::ofUser('What is the capital of France?'))], ['batch' => true])
            ->asJob();

        $this->assertSame('batch_123', $handle->getId());
        $this->assertSame('mistral-eu', $handle->getProvider());
        $this->assertSame(JobClient::KIND, $handle->get('kind'));
    }

    public function testTheJobClientResolvesTheHandlesThisBridgeHandsOut()
    {
        $handle = new JobHandle('batch_123', ['kind' => JobClient::KIND], 'mistral');

        $this->assertTrue(Factory::createJobClient('mistral-key')->supports($handle));
    }
}
