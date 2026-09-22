<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\HiggsfieldJobClient;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HiggsfieldJobClientTest extends TestCase
{
    private const BASE_URL = 'https://platform.higgsfield.ai';

    public function testItOnlySupportsHandlesCarryingAStatusPath()
    {
        $jobClient = new HiggsfieldJobClient(new MockHttpClient());

        $this->assertTrue($jobClient->supports(self::handle()));
        $this->assertFalse($jobClient->supports(new JobHandle('req-123', [], 'higgsfield')));
    }

    #[DataProvider('provideStates')]
    public function testGetStatusMapsTheProviderState(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient([
            new MockResponse(\sprintf('{"request_id": "req-123", "status": "%s"}', $raw)),
        ], self::BASE_URL);

        $status = (new HiggsfieldJobClient($httpClient))->getStatus(self::handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
    }

    /**
     * @return iterable<string, array{string, JobStateCase}>
     */
    public static function provideStates(): iterable
    {
        yield 'queued' => ['queued', JobStateCase::QUEUED];
        yield 'in_progress' => ['in_progress', JobStateCase::RUNNING];
        yield 'completed' => ['completed', JobStateCase::SUCCEEDED];
        yield 'failed' => ['failed', JobStateCase::FAILED];
        yield 'nsfw' => ['nsfw', JobStateCase::FAILED];
        yield 'unknown to this bridge' => ['throttled', JobStateCase::UNKNOWN];
    }

    public function testGetStatusPollsTheRequestOnceAndDoesNotWait()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://platform.higgsfield.ai/requests/req-123/status', $url);

            return new MockResponse('{"request_id": "req-123", "status": "in_progress"}');
        }, self::BASE_URL);

        (new HiggsfieldJobClient($httpClient))->getStatus(self::handle());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetStatusReportsTheDetailOfAFailedGeneration()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "failed", "detail": "generation crashed"}'),
        ], self::BASE_URL);

        $status = (new HiggsfieldJobClient($httpClient))->getStatus(self::handle());

        $this->assertTrue($status->isTerminal());
        $this->assertSame('generation crashed', $status->getError());
    }

    public function testGetStatusReportsTheErrorKeyOfAFailedGeneration()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "failed", "error": "Generation failed"}'),
        ], self::BASE_URL);

        $status = (new HiggsfieldJobClient($httpClient))->getStatus(self::handle());

        $this->assertSame('Generation failed', $status->getError());
    }

    public function testGetStatusFailsOnAnErrorTheSharedHandlingDoesNotKnow()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"detail": "Not enough credits"}', ['http_code' => 402]),
        ], self::BASE_URL);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Higgsfield API error (HTTP 402): "Not enough credits".');

        (new HiggsfieldJobClient($httpClient))->getStatus(self::handle());
    }

    public function testGetStatusReportsTheDetailOfAKnownError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"detail": "invalid credentials"}', ['http_code' => 401]),
        ], self::BASE_URL);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid credentials');

        (new HiggsfieldJobClient($httpClient))->getStatus(self::handle());
    }

    public function testGetResultFailsWhenTheMediaDownloadIsRefused()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "completed", "video": {"url": "https://cdn.higgsfield.ai/video.mp4"}}'),
            new MockResponse('Forbidden', ['http_code' => 403]),
        ], self::BASE_URL);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Higgsfield API error (HTTP 403): "Unknown error".');

        (new HiggsfieldJobClient($httpClient))->getResult(self::handle());
    }

    public function testGetResultDownloadsTheGeneratedVideo()
    {
        $videoContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/ocean.mp4');

        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "completed", "video": {"url": "https://cdn.higgsfield.ai/video.mp4"}}'),
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ], self::BASE_URL);

        $result = (new HiggsfieldJobClient($httpClient))->getResult(self::handle());

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame($videoContent, $result->getContent());
        $this->assertSame('video/mp4', $result->getMimeType());
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testGetResultDownloadsTheGeneratedImage()
    {
        $imageContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/image.jpg');

        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "completed", "images": [{"url": "https://cdn.higgsfield.ai/image.jpg"}]}'),
            new MockResponse($imageContent, ['response_headers' => ['content-type' => 'image/jpeg']]),
        ], self::BASE_URL);

        $result = (new HiggsfieldJobClient($httpClient))->getResult(self::handle());

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/jpeg', $result->getMimeType());
    }

    public function testGetResultThrowsWhenTheGenerationIsNotDone()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "in_progress"}'),
        ], self::BASE_URL);

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('The Higgsfield request "req-123" is not ready to be fetched, its status is "in_progress".');

        (new HiggsfieldJobClient($httpClient))->getResult(self::handle());
    }

    public function testGetResultThrowsWhenNoMediaUrlIsReturned()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "completed"}'),
        ], self::BASE_URL);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Higgsfield response does not contain any media URL.');

        (new HiggsfieldJobClient($httpClient))->getResult(self::handle());
    }

    private static function handle(): JobHandle
    {
        return new JobHandle('req-123', ['status_path' => 'requests/req-123/status'], 'higgsfield');
    }
}
