<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Batch;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Batch\JobClient;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\BatchItemCase;
use Symfony\AI\Platform\Result\BatchResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JobClientTest extends TestCase
{
    public function testItOnlySupportsItsOwnBatchHandles()
    {
        $jobClient = new JobClient(new MockHttpClient(), 'mistral-key');

        $this->assertTrue($jobClient->supports(new JobHandle('batch_123', ['kind' => JobClient::KIND])));
        $this->assertFalse($jobClient->supports(new JobHandle('441464884859075', ['query_path' => 'query/video_generation'])));
        // The OpenAI bridge's batches are not this client's to resolve, even though they look alike.
        $this->assertFalse($jobClient->supports(new JobHandle('batch_123', ['kind' => 'openai_batch'])));
    }

    #[TestWith(['QUEUED', JobStateCase::QUEUED])]
    #[TestWith(['RUNNING', JobStateCase::RUNNING])]
    #[TestWith(['CANCELLATION_REQUESTED', JobStateCase::RUNNING])]
    #[TestWith(['SUCCESS', JobStateCase::SUCCEEDED])]
    #[TestWith(['FAILED', JobStateCase::FAILED])]
    #[TestWith(['TIMEOUT_EXCEEDED', JobStateCase::EXPIRED])]
    #[TestWith(['CANCELLED', JobStateCase::CANCELED])]
    #[TestWith(['SOMETHING_NEW', JobStateCase::UNKNOWN])]
    public function testItMapsTheStatesMistralReports(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['id' => 'batch_123', 'status' => $raw])));

        $status = (new JobClient($httpClient, 'mistral-key'))->getStatus(self::handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
        $this->assertNull($status->getError());
    }

    /**
     * A job that ran out of its `timeout_hours` is gone, not failed: what it got through is still
     * there, and the rest was never sent.
     */
    public function testATimedOutBatchIsExpiredRatherThanFailed()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "batch_123", "status": "TIMEOUT_EXCEEDED"}'));

        $status = (new JobClient($httpClient, 'mistral-key'))->getStatus(self::handle());

        $this->assertTrue($status->is(JobStateCase::EXPIRED));
        $this->assertTrue($status->isTerminal());
    }

    public function testItReportsTheFailureMistralStates()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_123',
            'status' => 'FAILED',
            'errors' => [['message' => 'Invalid input file.', 'count' => 1]],
        ])));

        $status = (new JobClient($httpClient, 'mistral-key'))->getStatus(self::handle());

        $this->assertTrue($status->is(JobStateCase::FAILED));
        $this->assertSame('Invalid input file.', $status->getError());
    }

    public function testItAsksTheBatchOfTheConfiguredBaseUrl()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return new MockResponse('{"id": "batch_123", "status": "RUNNING"}');
        });

        (new JobClient($httpClient, 'mistral-key', 'https://mistral.example.com/'))->getStatus(self::handle());

        $this->assertSame(['GET https://mistral.example.com/v1/batch/jobs/batch_123'], $urls);
    }

    public function testItReadsTheResultsOfAFinishedBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'id' => 'batch_123',
                'status' => 'SUCCESS',
                'output_file' => 'file-out',
            ])),
            new MockResponse(self::outputLine('capital-fr', 'Paris')."\n".self::outputLine('capital-de', 'Berlin')."\n"),
        ]);

        $result = (new JobClient($httpClient, 'mistral-key'))->getResult(self::handle());

        $this->assertInstanceOf(BatchResult::class, $result);

        $items = iterator_to_array($result->getContent(), false);

        $this->assertCount(2, $items);
        $this->assertSame('capital-fr', $items[0]->getId());
        $this->assertTrue($items[0]->isSuccess());
        // Each item converts through the bridge's own converter, like a synchronous invocation would.
        $this->assertInstanceOf(TextResult::class, $items[0]->getResult());
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
        $this->assertSame('Berlin', $items[1]->getResult()->getContent());
    }

    public function testItReadsALastLineWithoutTrailingNewline()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "SUCCESS", "output_file": "file-out"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(1, $items);
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
    }

    public function testItDownloadsTheOutputFileAndTheErrorFile()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return match (\count($urls)) {
                1 => new MockResponse('{"id": "batch_123", "status": "SUCCESS", "output_file": "file-out", "error_file": "file-err"}'),
                default => new MockResponse(''),
            };
        });

        iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertSame([
            'https://api.mistral.ai/v1/batch/jobs/batch_123',
            'https://api.mistral.ai/v1/files/file-out/content',
            'https://api.mistral.ai/v1/files/file-err/content',
        ], $urls);
    }

    public function testItReportsTheRequestsThatFailedAlongTheOnesThatDidNot()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'id' => 'batch_123',
                'status' => 'SUCCESS',
                'output_file' => 'file-out',
                'error_file' => 'file-err',
            ])),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
            new MockResponse(implode("\n", [
                json_encode(['custom_id' => 'capital-xx', 'response' => ['status_code' => 400, 'body' => ['object' => 'error', 'message' => 'Unknown model.', 'type' => 'invalid_model']], 'error' => null]),
                json_encode(['custom_id' => 'capital-yy', 'response' => ['status_code' => 422], 'error' => ['code' => 'invalid_request', 'message' => 'Missing messages.']]),
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(3, $items);
        $this->assertTrue($items[0]->isSuccess());

        // Mistral's own failure test is a status code of 400 or above, even without an error on the line.
        $this->assertSame(BatchItemCase::ERRORED, $items[1]->getCase());
        $this->assertSame('capital-xx', $items[1]->getId());
        $this->assertSame('Unknown model.', $items[1]->getError());
        $this->assertSame('invalid_model', $items[1]->getRaw());

        $this->assertSame(BatchItemCase::ERRORED, $items[2]->getCase());
        $this->assertSame('Missing messages.', $items[2]->getError());
        $this->assertSame('invalid_request', $items[2]->getRaw());
    }

    public function testItTakesAPlainStringErrorAsTheWordingItself()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "SUCCESS", "error_file": "file-err"}'),
            new MockResponse(json_encode(['custom_id' => 'capital-xx', 'response' => ['status_code' => 500], 'error' => 'Service unavailable.'])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::ERRORED, $items[0]->getCase());
        $this->assertSame('Service unavailable.', $items[0]->getError());
        $this->assertSame('Service unavailable.', $items[0]->getRaw());
    }

    /**
     * Mistral states no per-request reason for a request it never sent, so the batch's own outcome
     * is what says whether a request failed or never left: an answered one carries the status code
     * of its answer, one that never left carries none.
     */
    public function testItReportsTheRequestsATimedOutBatchNeverSentAsExpired()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "TIMEOUT_EXCEEDED", "output_file": "file-out", "error_file": "file-err"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
            new MockResponse(json_encode([
                'custom_id' => 'capital-de',
                'response' => null,
                'error' => ['message' => 'The job timed out before this request was run.'],
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertTrue($items[0]->isSuccess());

        // Never sent, so it cost nothing and can be submitted again as it is.
        $this->assertSame(BatchItemCase::EXPIRED, $items[1]->getCase());
        $this->assertSame('capital-de', $items[1]->getId());
        $this->assertFalse($items[1]->is(BatchItemCase::ERRORED));
        // The item's own message is canned, so Mistral's wording is kept as the raw outcome.
        $this->assertSame('The job timed out before this request was run.', $items[1]->getRaw());
    }

    public function testItReportsTheRequestsACanceledBatchNeverSentAsCanceled()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "CANCELLED", "output_file": "file-out", "error_file": "file-err"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
            new MockResponse(json_encode([
                'custom_id' => 'capital-de',
                'response' => null,
                'error' => ['code' => 'job_cancelled', 'message' => 'The job was cancelled before this request was run.'],
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::CANCELED, $items[1]->getCase());
        $this->assertSame('capital-de', $items[1]->getId());
        // Mistral's own wording stays on the item, next to the normalized outcome.
        $this->assertSame('job_cancelled', $items[1]->getRaw());
    }

    /**
     * A request the model answered with an error is a failure of that request, not one the batch
     * never sent - even while the batch itself was canceled.
     */
    public function testARequestThatWasAnsweredStaysErroredInACanceledBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "CANCELLED", "error_file": "file-err"}'),
            new MockResponse(json_encode([
                'custom_id' => 'capital-xx',
                'response' => ['status_code' => 400, 'body' => ['message' => 'Unknown model.']],
                'error' => null,
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::ERRORED, $items[0]->getCase());
        $this->assertSame('Unknown model.', $items[0]->getError());
    }

    public function testAnErrorWithoutAStatusCodeStaysErroredInASucceededBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "SUCCESS", "error_file": "file-err"}'),
            new MockResponse(json_encode(['custom_id' => 'capital-xx', 'response' => null, 'error' => ['message' => 'The request could not be parsed.']])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::ERRORED, $items[0]->getCase());
        $this->assertSame('The request could not be parsed.', $items[0]->getError());
    }

    public function testItTurnsAnUnreadableResponseIntoAFailedItemRatherThanFailingTheBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "SUCCESS", "output_file": "file-out"}'),
            new MockResponse(implode("\n", [
                json_encode(['custom_id' => 'broken', 'response' => ['status_code' => 200, 'body' => ['id' => 'cmpl-1']], 'error' => null]),
                self::outputLine('capital-fr', 'Paris'),
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertFalse($items[0]->isSuccess());
        $this->assertSame('Response does not contain choices.', $items[0]->getError());
        $this->assertTrue($items[1]->isSuccess());
    }

    public function testItHandsOutWhatACanceledBatchGotThroughBeforeItStopped()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "CANCELLED", "output_file": "file-out"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(1, $items);
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
    }

    public function testItFailsWhenTheBatchHasNoResultsAtAll()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_123',
            'status' => 'FAILED',
            'errors' => [['message' => 'The input file is empty.']],
        ])));

        try {
            (new JobClient($httpClient, 'mistral-key'))->getResult(self::handle());
            $this->fail('Expected a JobFailedException.');
        } catch (JobFailedException $exception) {
            $this->assertSame('The Mistral batch "batch_123" has no results to fetch, its status is "FAILED". The input file is empty.', $exception->getMessage());
            $this->assertTrue($exception->getStatus()->is(JobStateCase::FAILED));
        }
    }

    public function testItRefusesABatchOfAnEndpointItCannotConvert()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "batch_123", "status": "SUCCESS", "output_file": "file-out"}'));
        $handle = new JobHandle('batch_123', ['kind' => JobClient::KIND, 'endpoint' => '/v1/embeddings']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Mistral batch "batch_123" was submitted to "/v1/embeddings", which this bridge cannot convert the results of.');

        (new JobClient($httpClient, 'mistral-key'))->getResult($handle);
    }

    public function testItFailsOnAResultFileThatIsNotJsonLines()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "SUCCESS", "output_file": "file-out"}'),
            new MockResponse('not json'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A line of the Mistral batch result file is not valid JSON');

        iterator_to_array((new JobClient($httpClient, 'mistral-key'))->getResult(self::handle())->getContent(), false);
    }

    public function testItReportsWhereTheBatchStandsAndHowFarItHasComeInOneRequest()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(json_encode([
                'id' => 'batch_123',
                'status' => 'RUNNING',
                'total_requests' => 200,
                'completed_requests' => 145,
                'succeeded_requests' => 142,
                'failed_requests' => 3,
            ]));
        });

        $progress = (new JobClient($httpClient, 'mistral-key'))->getProgress(self::handle());

        $this->assertSame(1, $requests);
        $this->assertSame('RUNNING', $progress['status']->getRaw());
        $this->assertSame(200, $progress['total']);
        // Mistral counts the failed requests as completed too, this contract keeps the two apart.
        $this->assertSame(142, $progress['completed']);
        $this->assertSame(3, $progress['failed']);
    }

    public function testItReportsNoProgressForABatchThatHasNotStarted()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "batch_123", "status": "QUEUED"}'));

        $progress = (new JobClient($httpClient, 'mistral-key'))->getProgress(self::handle());

        $this->assertSame([0, 0, 0], [$progress['total'], $progress['completed'], $progress['failed']]);
    }

    public function testItCancelsABatch()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return new MockResponse('{"id": "batch_123", "status": "CANCELLATION_REQUESTED"}');
        });

        $status = (new JobClient($httpClient, 'mistral-key'))->cancel(self::handle());

        $this->assertSame(['POST https://api.mistral.ai/v1/batch/jobs/batch_123/cancel'], $urls);
        $this->assertSame('CANCELLATION_REQUESTED', $status->getRaw());
        // The job is not gone yet, so a runner polling it keeps waiting for it to be.
        $this->assertFalse($status->isTerminal());
    }

    public function testItReportsAnHttpFailureOfTheCancellation()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"message": "Not found."}', ['http_code' => 404]));

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Not found.');

        (new JobClient($httpClient, 'mistral-key'))->cancel(self::handle());
    }

    private static function handle(): JobHandle
    {
        return new JobHandle('batch_123', ['kind' => JobClient::KIND, 'endpoint' => '/v1/chat/completions'], 'mistral', 86400);
    }

    private static function outputLine(string $customId, string $text): string
    {
        return json_encode([
            'id' => 'batch_req_'.$customId,
            'custom_id' => $customId,
            'response' => [
                'status_code' => 200,
                'body' => [
                    'id' => 'cmpl-'.$customId,
                    'object' => 'chat.completion',
                    'model' => 'mistral-large-latest',
                    'choices' => [[
                        'index' => 0,
                        'message' => ['role' => 'assistant', 'content' => $text],
                        'finish_reason' => 'stop',
                    ]],
                ],
            ],
            'error' => null,
        ]);
    }
}
