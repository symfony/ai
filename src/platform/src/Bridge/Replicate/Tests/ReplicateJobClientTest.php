<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Replicate\Client;
use Symfony\AI\Platform\Bridge\Replicate\ReplicateJobClient;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReplicateJobClientTest extends TestCase
{
    public function testItOnlySupportsHandlesCarryingAPredictionPath()
    {
        $jobClient = self::createJobClient(new MockHttpClient());

        $this->assertTrue($jobClient->supports(self::handle()));
        $this->assertFalse($jobClient->supports(new JobHandle('pred-123', [], 'replicate')));
    }

    #[DataProvider('provideStates')]
    public function testGetStatusMapsTheProviderState(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient(new MockResponse(\sprintf('{"id": "pred-123", "status": "%s"}', $raw)));

        $status = self::createJobClient($httpClient)->getStatus(self::handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
    }

    /**
     * @return iterable<string, array{string, JobStateCase}>
     */
    public static function provideStates(): iterable
    {
        yield 'starting' => ['starting', JobStateCase::QUEUED];
        yield 'processing' => ['processing', JobStateCase::RUNNING];
        yield 'succeeded' => ['succeeded', JobStateCase::SUCCEEDED];
        yield 'failed' => ['failed', JobStateCase::FAILED];
        yield 'canceled' => ['canceled', JobStateCase::CANCELED];
        yield 'unknown to this bridge' => ['rescheduled', JobStateCase::UNKNOWN];
    }

    public function testGetStatusCarriesTheProviderError()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "failed", "error": "Out of memory"}'));

        $status = self::createJobClient($httpClient)->getStatus(self::handle());

        $this->assertTrue($status->isTerminal());
        $this->assertSame('Out of memory', $status->getError());
    }

    public function testGetStatusFailsOnAnErrorTheSharedHandlingDoesNotKnow()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"title": "Insufficient credit", "detail": "You have insufficient credit to run this model."}', ['http_code' => 402]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Replicate API error (HTTP 402): "You have insufficient credit to run this model.".');

        self::createJobClient($httpClient)->getStatus(self::handle());
    }

    public function testGetStatusReportsTheDetailOfAKnownError()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"title": "Unauthenticated", "detail": "You did not pass a valid authentication token"}', ['http_code' => 401]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('You did not pass a valid authentication token');

        self::createJobClient($httpClient)->getStatus(self::handle());
    }

    public function testGetStatusPollsOnceAndDoesNotWait()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "processing"}'));

        self::createJobClient($httpClient)->getStatus(self::handle());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetResultJoinsTheOutputChunks()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "succeeded", "output": ["Hello", " ", "world"]}'));

        $result = self::createJobClient($httpClient)->getResult(self::handle());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testGetResultAcceptsAPlainStringOutput()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "succeeded", "output": "Hello world"}'));

        $result = self::createJobClient($httpClient)->getResult(self::handle());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testGetResultThrowsWhenThePredictionIsNotDone()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "processing"}'));

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('The Replicate prediction "pred-123" is not ready to be fetched, its status is "processing".');

        self::createJobClient($httpClient)->getResult(self::handle());
    }

    public function testGetResultThrowsWhenTheOutputIsMissing()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "succeeded"}'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Replicate prediction "pred-123" does not contain output.');

        self::createJobClient($httpClient)->getResult(self::handle());
    }

    private static function handle(): JobHandle
    {
        return new JobHandle('pred-123', ['prediction_path' => 'v1/predictions/pred-123'], 'replicate');
    }

    private static function createJobClient(MockHttpClient $httpClient): ReplicateJobClient
    {
        return new ReplicateJobClient(new Client($httpClient, 'test-key'));
    }
}
