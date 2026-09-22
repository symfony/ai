<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\VeniceJobClient;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VeniceJobClientTest extends TestCase
{
    private const BASE_URL = 'https://api.venice.ai/api/v1/';

    public function testItOnlySupportsHandlesCarryingTheQueuedModel()
    {
        $jobClient = new VeniceJobClient(new MockHttpClient());

        $this->assertTrue($jobClient->supports(self::handle()));
        $this->assertFalse($jobClient->supports(new JobHandle('q-1', [], 'venice')));
        // A key as generic as `model` could come from any bridge.
        $this->assertFalse($jobClient->supports(new JobHandle('q-1', ['model' => 'seedance-1-5-pro-text-to-video'], 'other')));
    }

    #[DataProvider('provideStates')]
    public function testGetStatusMapsTheProviderState(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(['status' => $raw])], self::BASE_URL);

        $status = self::createJobClient($httpClient)->getStatus(self::handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
    }

    /**
     * @return iterable<string, array{string, JobStateCase}>
     */
    public static function provideStates(): iterable
    {
        yield 'queued' => ['QUEUED', JobStateCase::QUEUED];
        yield 'processing' => ['PROCESSING', JobStateCase::RUNNING];
        yield 'failed' => ['FAILED', JobStateCase::FAILED];
        yield 'canceled' => ['CANCELED', JobStateCase::CANCELED];
        yield 'unknown to this bridge' => ['THROTTLED', JobStateCase::UNKNOWN];
    }

    public function testGetStatusReportsSuccessWhenVeniceDeliversTheVideo()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('binary-video-bytes', ['response_headers' => ['content-type' => 'video/mp4']]),
        ], self::BASE_URL);

        $status = self::createJobClient($httpClient)->getStatus(self::handle());

        $this->assertSame(JobStateCase::SUCCEEDED, $status->getCase());
        $this->assertSame('COMPLETED', $status->getRaw());
    }

    public function testGetStatusSendsTheQueueIdentifierAndModel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.venice.ai/api/v1/video/retrieve', $url);
            $this->assertIsString($options['body']);
            $this->assertSame(['model' => 'seedance-1-5-pro-text-to-video', 'queue_id' => 'q-1'], json_decode($options['body'], true));

            return new JsonMockResponse(['status' => 'PROCESSING']);
        }, self::BASE_URL);

        self::createJobClient($httpClient)->getStatus(self::handle());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetStatusCarriesTheProviderError()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(['status' => 'FAILED', 'error' => 'Content policy'])], self::BASE_URL);

        $status = self::createJobClient($httpClient)->getStatus(self::handle());

        $this->assertTrue($status->isTerminal());
        $this->assertSame('Content policy', $status->getError());
    }

    public function testGetStatusFailsOnAnErrorTheSharedHandlingDoesNotKnow()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['error' => 'Insufficient balance'], ['http_code' => 402]),
        ], self::BASE_URL);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Venice API error (HTTP 402): "Insufficient balance".');

        self::createJobClient($httpClient)->getStatus(self::handle());
    }

    public function testGetResultReturnsTheVideo()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('binary-video-bytes', ['response_headers' => ['content-type' => 'video/mp4']]),
        ], self::BASE_URL);

        $result = self::createJobClient($httpClient)->getResult(self::handle());

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('binary-video-bytes', $result->getContent());
        $this->assertSame('video/mp4', $result->getMimeType());
    }

    public function testGetResultThrowsWhileTheGenerationIsStillRunning()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(['status' => 'PROCESSING'])], self::BASE_URL);

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('The Venice video generation "q-1" is not ready to be fetched, its status is "PROCESSING".');

        self::createJobClient($httpClient)->getResult(self::handle());
    }

    public function testResolvingAHandleWithoutAModelThrows()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The job handle "q-1" does not carry the Venice model the generation was queued for.');

        self::createJobClient(new MockHttpClient([], self::BASE_URL))->getStatus(new JobHandle('q-1', [], 'venice'));
    }

    private static function createJobClient(MockHttpClient $httpClient): VeniceJobClient
    {
        return new VeniceJobClient($httpClient);
    }

    private static function handle(): JobHandle
    {
        return new JobHandle('q-1', ['queue_model' => 'seedance-1-5-pro-text-to-video'], 'venice');
    }
}
