<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Batch;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\JobClient;
use Symfony\AI\Platform\Exception\JobFailedException;
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
        $jobClient = new JobClient(new MockHttpClient(), 'sk-test');

        $this->assertTrue($jobClient->supports(new JobHandle('batch_123', ['kind' => JobClient::KIND])));
        $this->assertFalse($jobClient->supports(new JobHandle('441464884859075', ['query_path' => 'query/video_generation'])));
        // Another provider's batch would plausibly call itself that, and is not this client's to resolve.
        $this->assertFalse($jobClient->supports(new JobHandle('msgbatch_123', ['kind' => 'batch'])));
    }

    #[TestWith(['validating', JobStateCase::QUEUED])]
    #[TestWith(['in_progress', JobStateCase::RUNNING])]
    #[TestWith(['finalizing', JobStateCase::RUNNING])]
    #[TestWith(['cancelling', JobStateCase::RUNNING])]
    #[TestWith(['completed', JobStateCase::SUCCEEDED])]
    #[TestWith(['failed', JobStateCase::FAILED])]
    #[TestWith(['expired', JobStateCase::EXPIRED])]
    #[TestWith(['cancelled', JobStateCase::CANCELED])]
    #[TestWith(['something_new', JobStateCase::UNKNOWN])]
    public function testItMapsTheStatesOpenAiReports(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['id' => 'batch_123', 'status' => $raw])));

        $status = (new JobClient($httpClient, 'sk-test'))->getStatus(self::handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
        $this->assertNull($status->getError());
    }

    public function testItReportsTheFailureOpenAiStates()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_123',
            'status' => 'failed',
            'errors' => ['data' => [['code' => 'invalid_json_line', 'message' => 'Line 4 is not valid JSON.']]],
        ])));

        $status = (new JobClient($httpClient, 'sk-test'))->getStatus(self::handle());

        $this->assertTrue($status->is(JobStateCase::FAILED));
        $this->assertSame('Line 4 is not valid JSON.', $status->getError());
    }

    public function testItAsksTheBatchOfTheConfiguredRegion()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return new MockResponse('{"id": "batch_123", "status": "in_progress"}');
        });

        (new JobClient($httpClient, 'sk-test', 'EU'))->getStatus(self::handle());

        $this->assertSame(['GET https://eu.api.openai.com/v1/batches/batch_123'], $urls);
    }

    public function testItReadsTheResultsOfAFinishedBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'id' => 'batch_123',
                'status' => 'completed',
                'output_file_id' => 'file-out',
            ])),
            new MockResponse(self::outputLine('capital-fr', 'Paris')."\n".self::outputLine('capital-de', 'Berlin')."\n"),
        ]);

        $result = (new JobClient($httpClient, 'sk-test'))->getResult(self::handle());

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
            new MockResponse('{"id": "batch_123", "status": "completed", "output_file_id": "file-out"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-test'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(1, $items);
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
    }

    public function testItReportsTheRequestsThatFailedAlongTheOnesThatDidNot()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'id' => 'batch_123',
                'status' => 'completed',
                'output_file_id' => 'file-out',
                'error_file_id' => 'file-err',
            ])),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
            new MockResponse(implode("\n", [
                json_encode(['custom_id' => 'capital-xx', 'response' => ['status_code' => 400, 'body' => ['error' => ['code' => 'model_not_found', 'message' => 'Unknown model.']]], 'error' => null]),
                json_encode(['custom_id' => 'capital-yy', 'response' => null, 'error' => ['code' => 'invalid_request', 'message' => 'Missing body.']]),
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-test'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(3, $items);
        $this->assertTrue($items[0]->isSuccess());

        $this->assertSame(BatchItemCase::ERRORED, $items[1]->getCase());
        $this->assertSame('capital-xx', $items[1]->getId());
        $this->assertSame('Unknown model.', $items[1]->getError());
        $this->assertSame('model_not_found', $items[1]->getRaw());

        // An error code that is not one of the "never sent" ones stays an ordinary failure.
        $this->assertSame(BatchItemCase::ERRORED, $items[2]->getCase());
        $this->assertSame('Missing body.', $items[2]->getError());
        $this->assertSame('invalid_request', $items[2]->getRaw());
    }

    /**
     * The requests an expired batch never sent are written to the error file, documented as
     * `{"response": null, "error": {"code": "batch_expired", ...}}`.
     */
    public function testItReportsTheRequestsAnExpiredBatchNeverSentAsExpired()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "expired", "output_file_id": "file-out", "error_file_id": "file-err"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
            new MockResponse(json_encode([
                'id' => 'batch_req_123',
                'custom_id' => 'capital-de',
                'response' => null,
                'error' => ['code' => 'batch_expired', 'message' => 'This request could not be executed before the completion window expired.'],
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-test'))->getResult(self::handle())->getContent(), false);

        $this->assertTrue($items[0]->isSuccess());

        // Never sent, so it cost nothing and can be submitted again as it is.
        $this->assertSame(BatchItemCase::EXPIRED, $items[1]->getCase());
        $this->assertSame('capital-de', $items[1]->getId());
        $this->assertSame('batch_expired', $items[1]->getRaw());
        $this->assertFalse($items[1]->is(BatchItemCase::ERRORED));
    }

    #[TestWith(['batch_cancelled'])]
    #[TestWith(['batch_canceled'])]
    public function testItReportsTheRequestsACanceledBatchNeverSentAsCanceled(string $code)
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "cancelled", "output_file_id": "file-out", "error_file_id": "file-err"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
            new MockResponse(json_encode([
                'custom_id' => 'capital-de',
                'response' => null,
                'error' => ['code' => $code, 'message' => 'This request was cancelled before it could be executed.'],
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-test'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::CANCELED, $items[1]->getCase());
        $this->assertSame($code, $items[1]->getRaw());
    }

    public function testItTurnsAnUnreadableResponseIntoAFailedItemRatherThanFailingTheBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "completed", "output_file_id": "file-out"}'),
            new MockResponse(implode("\n", [
                json_encode(['custom_id' => 'broken', 'response' => ['status_code' => 200, 'body' => ['status' => 'completed']], 'error' => null]),
                self::outputLine('capital-fr', 'Paris'),
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-test'))->getResult(self::handle())->getContent(), false);

        $this->assertFalse($items[0]->isSuccess());
        $this->assertSame('Response does not contain output.', $items[0]->getError());
        $this->assertTrue($items[1]->isSuccess());
    }

    public function testItHandsOutWhatACanceledBatchGotThroughBeforeItStopped()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "cancelled", "output_file_id": "file-out"}'),
            new MockResponse(self::outputLine('capital-fr', 'Paris')),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-test'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(1, $items);
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
    }

    public function testItFailsWhenTheBatchHasNoResultsAtAll()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_123',
            'status' => 'failed',
            'errors' => ['data' => [['message' => 'The input file is empty.']]],
        ])));

        try {
            (new JobClient($httpClient, 'sk-test'))->getResult(self::handle());
            $this->fail('Expected a JobFailedException.');
        } catch (JobFailedException $exception) {
            $this->assertSame('The OpenAI batch "batch_123" has no results to fetch, its status is "failed". The input file is empty.', $exception->getMessage());
            $this->assertTrue($exception->getStatus()->is(JobStateCase::FAILED));
        }
    }

    public function testItRefusesABatchOfAnEndpointItCannotConvert()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "batch_123", "status": "completed", "output_file_id": "file-out"}'));
        $handle = new JobHandle('batch_123', ['kind' => JobClient::KIND, 'endpoint' => '/v1/embeddings']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The OpenAI batch "batch_123" was submitted to "/v1/embeddings", which this bridge cannot convert the results of.');

        (new JobClient($httpClient, 'sk-test'))->getResult($handle);
    }

    public function testItFailsOnAResultFileThatIsNotJsonLines()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "batch_123", "status": "completed", "output_file_id": "file-out"}'),
            new MockResponse('not json'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A line of the OpenAI batch result file is not valid JSON');

        iterator_to_array((new JobClient($httpClient, 'sk-test'))->getResult(self::handle())->getContent(), false);
    }

    public function testItReportsWhereTheBatchStandsAndHowFarItHasComeInOneRequest()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(json_encode([
                'id' => 'batch_123',
                'status' => 'in_progress',
                'request_counts' => ['total' => 200, 'completed' => 142, 'failed' => 3],
            ]));
        });

        $progress = (new JobClient($httpClient, 'sk-test'))->getProgress(self::handle());

        $this->assertSame(1, $requests);
        $this->assertSame('in_progress', $progress['status']->getRaw());
        $this->assertSame(200, $progress['total']);
        $this->assertSame(142, $progress['completed']);
        $this->assertSame(3, $progress['failed']);
    }

    public function testItReportsNoProgressForABatchThatHasNotStarted()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "batch_123", "status": "validating"}'));

        $progress = (new JobClient($httpClient, 'sk-test'))->getProgress(self::handle());

        $this->assertSame([0, 0, 0], [$progress['total'], $progress['completed'], $progress['failed']]);
    }

    public function testItCancelsABatch()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return new MockResponse('{"id": "batch_123", "status": "cancelling"}');
        });

        $status = (new JobClient($httpClient, 'sk-test'))->cancel(self::handle());

        $this->assertSame(['POST https://api.openai.com/v1/batches/batch_123/cancel'], $urls);
        $this->assertSame('cancelling', $status->getRaw());
        // The batch is not gone yet, so a runner polling it keeps waiting for it to be.
        $this->assertFalse($status->isTerminal());
    }

    private static function handle(): JobHandle
    {
        return new JobHandle('batch_123', ['kind' => JobClient::KIND, 'endpoint' => '/v1/responses'], 'openai', 86400);
    }

    private static function outputLine(string $customId, string $text): string
    {
        return json_encode([
            'id' => 'batch_req_'.$customId,
            'custom_id' => $customId,
            'response' => [
                'status_code' => 200,
                'body' => [
                    'status' => 'completed',
                    'output' => [[
                        'type' => 'message',
                        'id' => 'msg_'.$customId,
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => $text]],
                    ]],
                ],
            ],
            'error' => null,
        ]);
    }
}
