<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMaxJobClient;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MiniMaxJobClientTest extends TestCase
{
    public function testItOnlySupportsHandlesCarryingAQueryPath()
    {
        $jobClient = new MiniMaxJobClient(new MockHttpClient(), 'key');

        $this->assertTrue($jobClient->supports(new JobHandle('123', ['query_path' => 'query/video_generation'])));
        $this->assertFalse($jobClient->supports(new JobHandle('123')));
    }

    #[DataProvider('provideProviderStates')]
    public function testItTranslatesTheProvidersWordingIntoAState(string $raw, JobStateCase $expected)
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['status' => $raw]));
        $jobClient = new MiniMaxJobClient($httpClient, 'key');

        $status = $jobClient->getStatus($this->handle());

        $this->assertSame($expected, $status->getCase());
        $this->assertSame($raw, $status->getRaw(), 'the untouched provider wording is kept');
    }

    /**
     * @return iterable<string, array{string, JobStateCase}>
     */
    public static function provideProviderStates(): iterable
    {
        yield 'queueing' => ['Queueing', JobStateCase::QUEUED];
        yield 'preparing' => ['Preparing', JobStateCase::QUEUED];
        yield 'processing' => ['Processing', JobStateCase::RUNNING];
        yield 'success' => ['Success', JobStateCase::SUCCEEDED];
        yield 'fail' => ['Fail', JobStateCase::FAILED];
        yield 'expired' => ['Expired', JobStateCase::EXPIRED];
        yield 'something new' => ['Rescheduled', JobStateCase::UNKNOWN];
    }

    public function testItExposesTheProvidersFailureMessage()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'status' => 'Fail',
            'base_resp' => ['status_code' => 2013, 'status_msg' => 'invalid params'],
        ]));

        $status = (new MiniMaxJobClient($httpClient, 'key'))->getStatus($this->handle());

        $this->assertTrue($status->is(JobStateCase::FAILED));
        $this->assertSame('invalid params', $status->getError());
    }

    public function testItLooksUpTheFileAndDownloadsIt()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'Success', 'file_id' => '999']),
            new JsonMockResponse(['file' => ['download_url' => 'https://cdn.minimax.io/video.mp4']]),
            new MockResponse('FAKE_VIDEO'),
        ]);

        $result = (new MiniMaxJobClient($httpClient, 'key'))->getResult($this->handle());

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_VIDEO', $result->getContent());
        $this->assertSame('video/mp4', $result->getMimeType());
        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testItFallsBackToTheFileIdentifierFromTheSubmitResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'Success']),
            new JsonMockResponse(['file' => ['download_url' => 'https://cdn.minimax.io/audio.mp3']]),
            new MockResponse('FAKE_AUDIO'),
        ]);

        $handle = new JobHandle('123', [
            'query_path' => 'query/t2a_async_query_v2',
            'mime_type' => 'audio/mpeg',
            'file_id' => '456',
        ]);

        $result = (new MiniMaxJobClient($httpClient, 'key'))->getResult($handle);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_AUDIO', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    /**
     * The asynchronous speech endpoint delivers a tar, so what the caller gets has to be unpacked
     * out of it - otherwise async speech would produce an archive where synchronous speech produces
     * audio.
     */
    public function testItUnpacksTheRequestedMemberFromADownloadedArchive()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'Success', 'file_id' => '456']),
            new JsonMockResponse(['file' => ['download_url' => 'https://cdn.minimax.io/speech.tar']]),
            new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/minimax-async-speech.tar')),
        ]);

        $result = (new MiniMaxJobClient($httpClient, 'key'))->getResult($this->speechHandle());

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('ID3FAKE-MP3-PAYLOAD', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testItThrowsWhenTheArchiveDoesNotContainTheExpectedMember()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'Success', 'file_id' => '456']),
            new JsonMockResponse(['file' => ['download_url' => 'https://cdn.minimax.io/speech.tar']]),
            new MockResponse('not an archive'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains no "mp3" file');

        (new MiniMaxJobClient($httpClient, 'key'))->getResult($this->speechHandle());
    }

    public function testItRefusesToFetchAJobThatIsNotDone()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['status' => 'Processing']));

        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage('not ready to be fetched');

        (new MiniMaxJobClient($httpClient, 'key'))->getResult($this->handle());
    }

    public function testItThrowsWhenTheFinishedJobHasNoFile()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['status' => 'Success']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not return a file identifier');

        (new MiniMaxJobClient($httpClient, 'key'))->getResult($this->handle());
    }

    public function testItSurfacesHttpErrorsWhilePolling()
    {
        $httpClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));

        $this->expectException(ServerException::class);

        (new MiniMaxJobClient($httpClient, 'key'))->getStatus($this->handle());
    }

    /**
     * The behaviour the result converter used to implement itself, now assembled from the two pieces:
     * a job client that answers one question per call, and a runner that owns the waiting.
     */
    public function testItDrivesAJobToCompletionTogetherWithTheRunner()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'Preparing']),
            new JsonMockResponse(['status' => 'Processing']),
            new JsonMockResponse(['status' => 'Success', 'file_id' => '999']),
            new JsonMockResponse(['status' => 'Success', 'file_id' => '999']),
            new JsonMockResponse(['file' => ['download_url' => 'https://cdn.minimax.io/video.mp4']]),
            new MockResponse('FAKE_VIDEO'),
        ]);

        $jobClient = new MiniMaxJobClient($httpClient, 'key');
        $result = (new JobRunner(new MockClock()))->wait($jobClient, $this->handle());

        $this->assertSame('FAKE_VIDEO', $result->asBinary());
    }

    private function speechHandle(): JobHandle
    {
        return new JobHandle('123', [
            'query_path' => 'query/t2a_async_query_v2',
            'mime_type' => 'audio/mpeg',
            'archive_member' => 'mp3',
        ]);
    }

    private function handle(): JobHandle
    {
        return new JobHandle('123', ['query_path' => 'query/video_generation', 'mime_type' => 'video/mp4']);
    }
}
