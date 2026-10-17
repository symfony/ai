<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Batch\JobClient;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesTheJobClientResolvingTheBatchesOfThisBridge()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('{"id": "msgbatch_123", "processing_status": "ended"}');
        });

        $jobClient = Factory::createJobClient('sk-ant-test', $httpClient);

        $this->assertInstanceOf(JobClient::class, $jobClient);

        $handle = new JobHandle('msgbatch_123', ['kind' => JobClient::KIND]);

        $this->assertTrue($jobClient->supports($handle));
        $this->assertSame('ended', $jobClient->getStatus($handle)->getRaw());
        $this->assertSame(['https://api.anthropic.com/v1/messages/batches/msgbatch_123'], $urls);
    }

    public function testItCreatesAJobClientTalkingToAnAnthropicCompatibleEndpoint()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('{"id": "msgbatch_123", "processing_status": "ended"}');
        });

        Factory::createJobClient('sk-ant-test', $httpClient, 'https://gateway.example.com')
            ->getStatus(new JobHandle('msgbatch_123', ['kind' => JobClient::KIND]));

        $this->assertSame(['https://gateway.example.com/v1/messages/batches/msgbatch_123'], $urls);
    }
}
