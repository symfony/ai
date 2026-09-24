<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests\Batch;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Batch\JobClient;
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
        $jobClient = new JobClient(new MockHttpClient(), 'sk-ant-test');

        $this->assertTrue($jobClient->supports(new JobHandle('msgbatch_123', ['kind' => JobClient::KIND])));
        $this->assertFalse($jobClient->supports(new JobHandle('441464884859075', ['query_path' => 'query/video_generation'])));
        // OpenAI's batch is not this client's to resolve, even though it is a batch too.
        $this->assertFalse($jobClient->supports(new JobHandle('batch_123', ['kind' => 'openai_batch'])));
    }

    /**
     * Anthropic has no batch-level failure: a batch whose requests all errored, and one that was
     * canceled, both end as `ended`.
     */
    #[TestWith(['in_progress', JobStateCase::RUNNING])]
    #[TestWith(['canceling', JobStateCase::RUNNING])]
    #[TestWith(['ended', JobStateCase::SUCCEEDED])]
    #[TestWith(['something_new', JobStateCase::UNKNOWN])]
    public function testItMapsTheStatesAnthropicReports(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['id' => 'msgbatch_123', 'processing_status' => $raw])));

        $status = (new JobClient($httpClient, 'sk-ant-test'))->getStatus(self::handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw());
        $this->assertNull($status->getError());
    }

    public function testItAsksTheBatchOfTheConfiguredBaseUrl()
    {
        $request = '';
        $headers = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request, &$headers): MockResponse {
            $request = $method.' '.$url;
            $headers = $options['headers'];

            return new MockResponse('{"id": "msgbatch_123", "processing_status": "in_progress"}');
        });

        (new JobClient($httpClient, 'sk-ant-test', 'https://gateway.example.com'))->getStatus(self::handle());

        $this->assertSame('GET https://gateway.example.com/v1/messages/batches/msgbatch_123', $request);
        $this->assertContains('x-api-key: sk-ant-test', $headers);
        $this->assertContains('anthropic-version: 2023-06-01', $headers);
    }

    public function testItReadsTheResultsOfAFinishedBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'id' => 'msgbatch_123',
                'processing_status' => 'ended',
                'results_url' => 'https://api.anthropic.com/v1/messages/batches/msgbatch_123/results',
            ])),
            new MockResponse(self::resultLine('capital-fr', 'Paris')."\n".self::resultLine('capital-de', 'Berlin')."\n"),
        ]);

        $result = (new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle());

        $this->assertInstanceOf(BatchResult::class, $result);

        $items = iterator_to_array($result->getContent(), false);

        $this->assertCount(2, $items);
        $this->assertSame('capital-fr', $items[0]->getId());
        $this->assertTrue($items[0]->isSuccess());
        // Each item converts through the bridge's own converter, like a synchronous invocation would.
        $this->assertInstanceOf(TextResult::class, $items[0]->getResult());
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
        $this->assertSame(BatchItemCase::SUCCEEDED, $items[0]->getCase());
        // The untouched outcome is Anthropic's own result type.
        $this->assertSame('succeeded', $items[0]->getRaw());
        $this->assertSame('Berlin', $items[1]->getResult()->getContent());
    }

    public function testItStreamsTheResultsInsteadOfLoadingThemAtOnce()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse((static function (): \Generator {
                yield self::resultLine('capital-fr', 'Paris')."\n";
                yield self::resultLine('capital-de', 'Berlin')."\n";
            })()),
        ]);

        $items = (new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent();

        $seen = [];

        foreach ($items as $item) {
            $seen[] = $item->getId();
        }

        $this->assertSame(['capital-fr', 'capital-de'], $seen);
    }

    public function testItReadsALastLineWithoutTrailingNewline()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse(self::resultLine('capital-fr', 'Paris')),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(1, $items);
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
    }

    public function testItFetchesTheResultsFromTheDocumentedEndpoint()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return str_ends_with($url, '/results')
                ? new MockResponse(self::resultLine('capital-fr', 'Paris'))
                : new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://storage.example.com/opaque"}');
        });

        iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        // `results_url` says the results are there; they are fetched from the configured base URL,
        // so the credentials never travel anywhere else.
        $this->assertSame([
            'GET https://api.anthropic.com/v1/messages/batches/msgbatch_123',
            'GET https://api.anthropic.com/v1/messages/batches/msgbatch_123/results',
        ], $urls);
    }

    public function testItReportsTheRequestsThatErroredAlongTheOnesThatDidNot()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse(implode("\n", [
                self::resultLine('capital-fr', 'Paris'),
                json_encode([
                    'custom_id' => 'capital-xx',
                    'result' => [
                        'type' => 'errored',
                        'error' => ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'max_tokens must be at least 1.']],
                    ],
                ]),
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(2, $items);
        $this->assertTrue($items[0]->isSuccess());

        $this->assertSame(BatchItemCase::ERRORED, $items[1]->getCase());
        $this->assertSame('capital-xx', $items[1]->getId());
        $this->assertSame('errored', $items[1]->getRaw());
        // Anthropic's own error type tells a malformed request from a transient server error.
        $this->assertSame('[invalid_request_error] max_tokens must be at least 1.', $items[1]->getError());
    }

    /**
     * A request the batch never sent is not a failed one: it cost nothing and can be submitted
     * again as it is.
     */
    #[TestWith(['canceled', BatchItemCase::CANCELED])]
    #[TestWith(['expired', BatchItemCase::EXPIRED])]
    public function testItReportsTheRequestsTheBatchNeverSent(string $type, BatchItemCase $expected)
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse(json_encode(['custom_id' => 'capital-de', 'result' => ['type' => $type]])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        $this->assertSame($expected, $items[0]->getCase());
        $this->assertSame('capital-de', $items[0]->getId());
        $this->assertSame($type, $items[0]->getRaw());
        $this->assertFalse($items[0]->is(BatchItemCase::ERRORED));
        $this->assertNotNull($items[0]->getError());
    }

    public function testItReportsAnOutcomeItDoesNotKnowAsUnknown()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse(json_encode(['custom_id' => 'capital-de', 'result' => ['type' => 'throttled']])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::UNKNOWN, $items[0]->getCase());
        $this->assertSame('throttled', $items[0]->getRaw());
        $this->assertSame('The batch reported the outcome "throttled" for this request, which this bridge does not know.', $items[0]->getError());
    }

    public function testItTurnsAnUnconvertibleMessageIntoAFailedItemRatherThanFailingTheBatch()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse(implode("\n", [
                json_encode(['custom_id' => 'broken', 'result' => ['type' => 'succeeded', 'message' => ['id' => 'msg_broken', 'content' => []]]]),
                self::resultLine('capital-fr', 'Paris'),
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        $this->assertFalse($items[0]->isSuccess());
        $this->assertSame('Response does not contain any content.', $items[0]->getError());
        // Anthropic answered this request; failing to read its answer is this bridge's problem, so
        // the item does not claim the provider reported a succeeded outcome.
        $this->assertNull($items[0]->getRaw());
        $this->assertTrue($items[1]->isSuccess());
    }

    public function testItTurnsASucceededResultWithoutAMessageIntoAFailedItem()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse(json_encode(['custom_id' => 'broken', 'result' => ['type' => 'succeeded']])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        $this->assertSame(BatchItemCase::ERRORED, $items[0]->getCase());
        $this->assertSame('The successful result of the request does not contain a message.', $items[0]->getError());
        $this->assertNull($items[0]->getRaw());
    }

    /**
     * A canceled batch ends as `ended` and hands out the requests it got through, so the results
     * are fetched on the results being there rather than on the batch having succeeded.
     */
    public function testItHandsOutWhatACanceledBatchGotThroughBeforeItStopped()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'id' => 'msgbatch_123',
                'processing_status' => 'ended',
                'cancel_initiated_at' => '2024-09-24T18:40:00.000000Z',
                'request_counts' => ['processing' => 0, 'succeeded' => 1, 'errored' => 0, 'canceled' => 1, 'expired' => 0],
                'results_url' => 'https://api.anthropic.com/results',
            ])),
            new MockResponse(implode("\n", [
                self::resultLine('capital-fr', 'Paris'),
                json_encode(['custom_id' => 'capital-de', 'result' => ['type' => 'canceled']]),
            ])),
        ]);

        $items = iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);

        $this->assertCount(2, $items);
        $this->assertSame('Paris', $items[0]->getResult()->getContent());
        $this->assertSame(BatchItemCase::CANCELED, $items[1]->getCase());
    }

    public function testItFailsWhenTheBatchHasNoResultsAtAll()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_123',
            'processing_status' => 'in_progress',
            'results_url' => null,
        ])));

        try {
            (new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle());
            $this->fail('Expected a JobFailedException.');
        } catch (JobFailedException $exception) {
            $this->assertSame('The Anthropic batch "msgbatch_123" has no results to fetch, its status is "in_progress".', $exception->getMessage());
            $this->assertTrue($exception->getStatus()->is(JobStateCase::RUNNING));
        }
    }

    public function testItFailsOnResultsThatAreNotJsonLines()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "msgbatch_123", "processing_status": "ended", "results_url": "https://api.anthropic.com/results"}'),
            new MockResponse('not json'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A line of the Anthropic batch results is not valid JSON');

        iterator_to_array((new JobClient($httpClient, 'sk-ant-test'))->getResult(self::handle())->getContent(), false);
    }

    public function testItReportsWhereTheBatchStandsAndHowFarItHasComeInOneRequest()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(json_encode([
                'id' => 'msgbatch_123',
                'processing_status' => 'in_progress',
                'request_counts' => ['processing' => 55, 'succeeded' => 142, 'errored' => 3, 'canceled' => 0, 'expired' => 0],
            ]));
        });

        $progress = (new JobClient($httpClient, 'sk-ant-test'))->getProgress(self::handle());

        $this->assertSame(1, $requests);
        $this->assertSame('in_progress', $progress['status']->getRaw());
        // Anthropic states no total of its own, so it is the sum of the five counts it reports.
        $this->assertSame(200, $progress['total']);
        $this->assertSame(142, $progress['completed']);
        $this->assertSame(3, $progress['failed']);
    }

    /**
     * The requests a canceled or expired batch never sent are neither completed nor failed: they
     * cost nothing and can be submitted again, so they only count towards the total.
     */
    public function testItCountsTheRequestsTheBatchNeverSentAsNeitherCompletedNorFailed()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_123',
            'processing_status' => 'ended',
            'request_counts' => ['processing' => 0, 'succeeded' => 6, 'errored' => 1, 'canceled' => 2, 'expired' => 1],
        ])));

        $progress = (new JobClient($httpClient, 'sk-ant-test'))->getProgress(self::handle());

        $this->assertSame([10, 6, 1], [$progress['total'], $progress['completed'], $progress['failed']]);
    }

    public function testItReportsNoProgressForABatchThatStatesNoCounts()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "msgbatch_123", "processing_status": "in_progress"}'));

        $progress = (new JobClient($httpClient, 'sk-ant-test'))->getProgress(self::handle());

        $this->assertSame([0, 0, 0], [$progress['total'], $progress['completed'], $progress['failed']]);
    }

    public function testItCancelsABatch()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return new MockResponse('{"id": "msgbatch_123", "processing_status": "canceling"}');
        });

        $status = (new JobClient($httpClient, 'sk-ant-test'))->cancel(self::handle());

        $this->assertSame(['POST https://api.anthropic.com/v1/messages/batches/msgbatch_123/cancel'], $urls);
        $this->assertSame('canceling', $status->getRaw());
        // The batch is still finishing what it started, so a runner polling it keeps waiting.
        $this->assertFalse($status->isTerminal());
        $this->assertSame(JobStateCase::RUNNING, $status->getCase());
    }

    private static function handle(): JobHandle
    {
        return new JobHandle('msgbatch_123', ['kind' => JobClient::KIND], 'anthropic', 86400, 60.0);
    }

    private static function resultLine(string $customId, string $text): string
    {
        return json_encode([
            'custom_id' => $customId,
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'id' => 'msg_'.$customId,
                    'type' => 'message',
                    'role' => 'assistant',
                    'model' => 'claude-3-5-sonnet-latest',
                    'content' => [['type' => 'text', 'text' => $text]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ],
            ],
        ]);
    }
}
