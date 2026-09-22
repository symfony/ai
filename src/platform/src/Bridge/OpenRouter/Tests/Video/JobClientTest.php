<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Video;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenRouter\Video\JobClient;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class JobClientTest extends TestCase
{
    public function testSupportsOnlyOpenRouterVideoHandles()
    {
        $client = new JobClient(new MockHttpClient());

        $this->assertTrue($client->supports(new JobHandle('job-1', ['type' => JobClient::TYPE])));
        $this->assertFalse($client->supports(new JobHandle('job-1', ['query_path' => 'query/video_generation'])));
    }

    /**
     * @return iterable<string, array{string, JobStateCase}>
     */
    public static function provideStates(): iterable
    {
        yield 'pending' => ['pending', JobStateCase::QUEUED];
        yield 'in progress' => ['in_progress', JobStateCase::RUNNING];
        yield 'completed' => ['completed', JobStateCase::SUCCEEDED];
        yield 'failed' => ['failed', JobStateCase::FAILED];
        yield 'unknown' => ['something_new', JobStateCase::UNKNOWN];
    }

    #[DataProvider('provideStates')]
    public function testMapsStatus(string $raw, JobStateCase $expected)
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl, $raw) {
            $capturedUrl = $url;

            return new JsonMockResponse(['id' => 'job-1', 'status' => $raw]);
        });

        $status = (new JobClient($httpClient, 'my-api-key'))->getStatus($this->createHandle());

        $this->assertSame('https://openrouter.ai/api/v1/videos/job-1', $capturedUrl);
        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
    }

    public function testExposesTheProviderError()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-1', 'status' => 'failed', 'error' => 'Content policy violation']),
        ]);

        $status = (new JobClient($httpClient))->getStatus($this->createHandle());

        $this->assertTrue($status->is(JobStateCase::FAILED));
        $this->assertSame('Content policy violation', $status->getError());
    }

    public function testDownloadsTheVideoOnceCompleted()
    {
        $videoContent = file_get_contents(\dirname(__DIR__, 7).'/fixtures/ocean.mp4');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'id' => 'job-1',
                'status' => 'completed',
                'unsigned_urls' => ['https://openrouter.ai/api/v1/videos/job-1/content?index=0'],
            ]),
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $result = (new JobClient($httpClient, 'my-api-key'))->getResult($this->createHandle());

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame($videoContent, $result->getContent());
        $this->assertSame('video/mp4', $result->getMimeType());
    }

    public function testFallsBackToTheContentEndpointWithoutDownloadUrl()
    {
        $capturedUrls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrls) {
            $capturedUrls[] = $url;

            return 1 === \count($capturedUrls)
                ? new JsonMockResponse(['id' => 'job-1', 'status' => 'completed', 'unsigned_urls' => []])
                : new MockResponse('binary', ['response_headers' => ['content-type' => 'video/mp4']]);
        });

        (new JobClient($httpClient))->getResult($this->createHandle());

        $this->assertSame('https://openrouter.ai/api/v1/videos/job-1/content?index=0', $capturedUrls[1]);
    }

    public function testThrowsWhenFetchingAnUnfinishedJob()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-1', 'status' => 'in_progress']),
        ]);

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('The OpenRouter video job "job-1" is not ready to be fetched, its status is "in_progress".');

        (new JobClient($httpClient))->getResult($this->createHandle());
    }

    public function testRunnerPollsUntilCompleted()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'job-1', 'status' => 'pending']),
            new JsonMockResponse(['id' => 'job-1', 'status' => 'in_progress']),
            new JsonMockResponse(['id' => 'job-1', 'status' => 'completed']),
            new JsonMockResponse(['id' => 'job-1', 'status' => 'completed']),
            new MockResponse('binary', ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $result = (new JobRunner(new MockClock()))->wait(new JobClient($httpClient), $this->createHandle());

        $this->assertSame('binary', $result->asBinary());
        $this->assertSame(5, $httpClient->getRequestsCount());
    }

    private function createHandle(): JobHandle
    {
        return new JobHandle('job-1', ['type' => JobClient::TYPE], 'openrouter', 600);
    }
}
