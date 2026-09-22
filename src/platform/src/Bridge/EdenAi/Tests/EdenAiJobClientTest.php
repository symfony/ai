<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\EdenAiJobClient;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\JobTimeoutException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class EdenAiJobClientTest extends TestCase
{
    public function testItCreatesHandlesNamingTheProvider()
    {
        $client = new EdenAiJobClient(new MockHttpClient(), 'test-key', 'https://api.edenai.run', 'my-edenai');

        $handle = $client->createHandle('job-123', ['subfeature' => 'speech_to_text_async'], 90);

        $this->assertSame('job-123', $handle->getId());
        $this->assertSame('my-edenai', $handle->getProvider());
        $this->assertSame(90, $handle->getMaxDuration());
        $this->assertTrue($client->supports($handle));
    }

    public function testItDoesNotSupportHandlesOfOtherBridges()
    {
        $client = new EdenAiJobClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new JobHandle('task-1', ['query_path' => 'query/video_generation'])));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideStates')]
    public function testItTranslatesTheProviderVocabulary(array $payload, JobStateCase $expected, ?string $error)
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($payload));
        $client = new EdenAiJobClient($httpClient, 'test-key');

        $status = $client->getStatus($this->handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($payload['status'], $status->getRaw());
        $this->assertSame($error, $status->getError());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: JobStateCase, 2: string|null}>
     */
    public static function provideStates(): iterable
    {
        // The three values UniversalAIAsyncResponse.status is documented to take.
        yield 'processing' => [['status' => 'processing'], JobStateCase::RUNNING, null];
        yield 'success' => [['status' => 'success'], JobStateCase::SUCCEEDED, null];
        yield 'fail' => [['status' => 'fail', 'error' => ['message' => 'Provider is down']], JobStateCase::FAILED, 'Provider is down'];
        yield 'fail with a plain error string' => [['status' => 'fail', 'error' => 'Bad audio'], JobStateCase::FAILED, 'Bad audio'];
        yield 'undocumented state stays non-terminal' => [['status' => 'throttled'], JobStateCase::UNKNOWN, null];
    }

    public function testItPollsTheJobUnderItsPublicId()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requests) {
            $requests[] = [$method, $url];

            return new JsonMockResponse(['status' => 'processing']);
        });

        $client = new EdenAiJobClient($httpClient, 'test-key');
        $client->getStatus($this->handle('job with spaces'));

        $this->assertSame(['GET', 'https://api.edenai.run/v3/universal-ai/async/job%20with%20spaces'], $requests[0]);
    }

    public function testItFetchesTheTranscriptionOfAFinishedJob()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'status' => 'success',
            'provider' => 'deepgram',
            'cost' => '0.0008',
            'output' => [
                'text' => 'Wishing you a merry Christmas.',
                'diarization' => ['total_speakers' => 1],
            ],
        ]));

        $result = (new EdenAiJobClient($httpClient, 'test-key'))->getResult($this->handle());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Wishing you a merry Christmas.', $result->getContent());
        $this->assertSame('deepgram', $result->getMetadata()->get('provider'));
        $this->assertSame(0.0008, $result->getMetadata()->get('cost'));
        $this->assertSame(['total_speakers' => 1], $result->getMetadata()->get('diarization'));
    }

    public function testItRefusesToFetchAJobThatDidNotSucceed()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['status' => 'processing']));

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('The Eden AI job "job-123" is not ready to be fetched, its status is "processing".');

        (new EdenAiJobClient($httpClient, 'test-key'))->getResult($this->handle());
    }

    public function testItThrowsWhenAFinishedJobCarriesNoText()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['status' => 'success', 'output' => []]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The finished Eden AI job "job-123" does not contain text.');

        (new EdenAiJobClient($httpClient, 'test-key'))->getResult($this->handle());
    }

    public function testItThrowsOnASubfeatureItCannotRead()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['status' => 'success', 'output' => ['text' => 'x']]));
        $handle = new JobHandle('job-123', ['subfeature' => 'video_generation_async']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Eden AI job "job-123" is of subfeature "video_generation_async", which this client cannot read.');

        (new EdenAiJobClient($httpClient, 'test-key'))->getResult($handle);
    }

    public function testItMapsAnErrorResponseOntoAPlatformException()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['detail' => 'Invalid token'], ['http_code' => 401]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token');

        (new EdenAiJobClient($httpClient, 'test-key'))->getStatus($this->handle());
    }

    /**
     * The point of the handle: a caller decides how long to wait, and the runner does the sleeping.
     */
    public function testItIsWaitedForByTheJobRunner()
    {
        $responses = [
            new JsonMockResponse(['status' => 'processing']),
            new JsonMockResponse(['status' => 'processing']),
            new JsonMockResponse(['status' => 'success']),
            new JsonMockResponse(['status' => 'success', 'output' => ['text' => 'Done']]),
        ];
        $httpClient = new MockHttpClient(static function () use (&$responses) {
            return array_shift($responses);
        });

        $client = new EdenAiJobClient($httpClient, 'test-key');
        $result = (new JobRunner(new MockClock()))->wait($client, $this->handle());

        $this->assertSame('Done', $result->asText());
    }

    public function testTheRunnerGivesUpAfterTheHandlesMaxDuration()
    {
        $httpClient = new MockHttpClient(static fn () => new JsonMockResponse(['status' => 'processing']));
        $client = new EdenAiJobClient($httpClient, 'test-key');

        $this->expectException(JobTimeoutException::class);

        (new JobRunner(new MockClock()))->wait($client, $client->createHandle('job-123', ['subfeature' => 'speech_to_text_async'], 3));
    }

    private function handle(string $id = 'job-123'): JobHandle
    {
        return new JobHandle($id, ['subfeature' => 'speech_to_text_async'], 'edenai', 120);
    }
}
