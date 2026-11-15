<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Batch;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Batch\JobClient;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\BatchItemCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class JobClientTest extends TestCase
{
    public function testItOnlySupportsItsOwnBatchHandles()
    {
        $jobClient = new JobClient(new MockHttpClient(), 'key');

        $this->assertTrue($jobClient->supports(new JobHandle('batches/123', ['kind' => JobClient::KIND])));
        $this->assertFalse($jobClient->supports(new JobHandle('batch_123', ['kind' => 'openai_batch'])));
    }

    #[TestWith(['BATCH_STATE_PENDING', JobStateCase::QUEUED])]
    #[TestWith(['BATCH_STATE_RUNNING', JobStateCase::RUNNING])]
    #[TestWith(['BATCH_STATE_SUCCEEDED', JobStateCase::SUCCEEDED])]
    #[TestWith(['JOB_STATE_SUCCEEDED', JobStateCase::SUCCEEDED])]
    #[TestWith(['BATCH_STATE_FAILED', JobStateCase::FAILED])]
    #[TestWith(['BATCH_STATE_CANCELLED', JobStateCase::CANCELED])]
    #[TestWith(['BATCH_STATE_EXPIRED', JobStateCase::EXPIRED])]
    #[TestWith(['BATCH_STATE_NEW', JobStateCase::UNKNOWN])]
    public function testItMapsTheStatesGeminiReports(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['name' => 'batches/123', 'metadata' => ['state' => $raw]]));

        $status = (new JobClient($httpClient, 'key'))->getStatus(self::handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
    }

    public function testItReadsTheStateOfABatchResource()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['name' => 'batches/123', 'state' => 'JOB_STATE_RUNNING']));

        $this->assertTrue((new JobClient($httpClient, 'key'))->getStatus(self::handle())->is(JobStateCase::RUNNING));
    }

    public function testItReportsTheFailureGeminiStates()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'name' => 'batches/123',
            'metadata' => ['state' => 'BATCH_STATE_FAILED'],
            'error' => ['code' => 3, 'message' => 'Invalid request.'],
        ]));

        $status = (new JobClient($httpClient, 'key'))->getStatus(self::handle());

        $this->assertSame('Invalid request.', $status->getError());
    }

    public function testItAsksTheConfiguredEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://gemini.example.com/v1beta/batches/123', $url);
            $this->assertContains('x-goog-api-key: key', $options['headers']);

            return new JsonMockResponse(['metadata' => ['state' => 'BATCH_STATE_RUNNING']]);
        });

        (new JobClient($httpClient, 'key', 'https://gemini.example.com/'))->getStatus(self::handle());
    }

    public function testItReportsProgress()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'metadata' => [
                'state' => 'BATCH_STATE_RUNNING',
                'batchStats' => ['requestCount' => '10', 'successfulRequestCount' => '6', 'failedRequestCount' => '1', 'pendingRequestCount' => '3'],
            ],
        ]));

        $progress = (new JobClient($httpClient, 'key'))->getProgress(self::handle());

        $this->assertTrue($progress['status']->is(JobStateCase::RUNNING));
        $this->assertSame(10, $progress['total']);
        $this->assertSame(6, $progress['completed']);
        $this->assertSame(1, $progress['failed']);
    }

    public function testItCancelsTheBatch()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requests): JsonMockResponse {
            $requests[] = $method.' '.$url;

            return new JsonMockResponse(['metadata' => ['state' => 'BATCH_STATE_CANCELLED']]);
        });

        $status = (new JobClient($httpClient, 'key'))->cancel(self::handle());

        $this->assertSame(['POST https://generativelanguage.googleapis.com/v1beta/batches/123:cancel', 'GET https://generativelanguage.googleapis.com/v1beta/batches/123'], $requests);
        $this->assertTrue($status->is(JobStateCase::CANCELED));
    }

    public function testItReadsInlinedResponses()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'metadata' => ['state' => 'BATCH_STATE_SUCCEEDED'],
            'response' => ['inlinedResponses' => ['inlinedResponses' => [
                ['metadata' => ['key' => 'capital-fr'], 'response' => ['candidates' => [['content' => ['parts' => [['text' => 'Paris']]], 'finishReason' => 'STOP']]]],
                ['metadata' => ['key' => 'capital-de'], 'error' => ['code' => 3, 'message' => 'Bad request.']],
                ['response' => ['candidates' => [['content' => ['parts' => [['text' => 'Rome']]]]]]],
            ]]],
        ]));

        $items = iterator_to_array((new JobClient($httpClient, 'key'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(3, $items);
        $this->assertSame('capital-fr', $items[0]->getId());
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
        $this->assertSame('capital-de', $items[1]->getId());
        $this->assertSame(BatchItemCase::ERRORED, $items[1]->getCase());
        $this->assertSame('Bad request.', $items[1]->getError());
        $this->assertSame('2', $items[2]->getId());
        $this->assertSame('Rome', $items[2]->getResult()->getContent());
    }

    public function testItReadsAResponsesFile()
    {
        $lines = json_encode(['key' => 'a', 'response' => ['candidates' => [['content' => ['parts' => [['text' => 'one']]]]]]])."\n\n"
            .json_encode(['key' => 'b', 'error' => ['code' => 8, 'message' => 'Quota.']]);

        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requests, $lines): MockResponse {
            $requests[] = $method.' '.$url;

            return str_contains($url, 'download')
                ? new MockResponse($lines)
                : new JsonMockResponse(['metadata' => ['state' => 'BATCH_STATE_SUCCEEDED'], 'response' => ['responsesFile' => 'files/out-1']]);
        });

        $items = iterator_to_array((new JobClient($httpClient, 'key'))->getResult(self::handle())->getContent(), false);

        $this->assertSame('GET https://generativelanguage.googleapis.com/download/v1beta/files/out-1:download?alt=media', $requests[1]);
        $this->assertSame('a', $items[0]->getId());
        $this->assertSame('one', $items[0]->getResult()->getContent());
        $this->assertSame('b', $items[1]->getId());
        $this->assertSame('Quota.', $items[1]->getError());
    }

    public function testItThrowsWhenThereAreNoResultsToFetch()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['metadata' => ['state' => 'BATCH_STATE_RUNNING']]));

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('The Gemini batch "batches/123" has no results to fetch, its status is "BATCH_STATE_RUNNING".');

        (new JobClient($httpClient, 'key'))->getResult(self::handle());
    }

    public function testAnUnreadableResponseOnlyFailsItsOwnItem()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'metadata' => ['state' => 'BATCH_STATE_SUCCEEDED'],
            'response' => ['inlinedResponses' => ['inlinedResponses' => [
                ['metadata' => ['key' => 'broken'], 'response' => ['unexpected' => true]],
                ['metadata' => ['key' => 'empty']],
                ['metadata' => ['key' => 'ok'], 'response' => ['candidates' => [['content' => ['parts' => [['text' => 'fine']]]]]]],
            ]]],
        ]));

        $items = iterator_to_array((new JobClient($httpClient, 'key'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::ERRORED, $items[0]->getCase());
        $this->assertSame(BatchItemCase::ERRORED, $items[1]->getCase());
        $this->assertSame('The entry holds neither a response nor an error.', $items[1]->getError());
        $this->assertTrue($items[2]->isSuccess());
    }

    public function testItRejectsAResponsesFileLineThatIsNotJson()
    {
        $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => str_contains($url, 'download')
            ? new MockResponse("not json\n")
            : new JsonMockResponse(['metadata' => ['state' => 'BATCH_STATE_SUCCEEDED'], 'response' => ['responsesFile' => 'files/out-1']]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not valid JSON');

        iterator_to_array((new JobClient($httpClient, 'key'))->getResult(self::handle())->getContent());
    }

    public function testItEncodesTheHandleInTheUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/batches/a%3Fb%20c', $url);

            return new JsonMockResponse(['metadata' => ['state' => 'BATCH_STATE_RUNNING']]);
        });

        (new JobClient($httpClient, 'key'))->getStatus(new JobHandle('batches/a?b c', ['kind' => JobClient::KIND]));
    }

    private static function handle(): JobHandle
    {
        return new JobHandle('batches/123', ['kind' => JobClient::KIND], 'gemini');
    }
}
