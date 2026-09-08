<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Test\Replay;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\SseStream;
use Symfony\AI\Platform\Test\Replay\CassetteHttpClient;
use Symfony\AI\Platform\Test\Replay\HttpCassette;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CassetteHttpClientTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/ai-cassette-'.bin2hex(random_bytes(6)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testRecordsRealResponseAndReturnsBufferedCopy()
    {
        $realClient = new MockHttpClient(new JsonMockResponse(['foo' => 'bar']));
        $client = new CassetteHttpClient(new HttpCassette($this->path), $realClient, record: true);

        $response = $client->request('POST', 'https://api.mistral.ai/v1/chat/completions', [
            'auth_bearer' => 'sk-secret',
            'json' => ['model' => 'mistral-large-latest'],
        ]);

        $this->assertSame(['foo' => 'bar'], $response->toArray());

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('{"foo":"bar"}', $data['interactions'][0]['response']['body']);
        $this->assertStringNotContainsString('sk-secret', (string) file_get_contents($this->path));
    }

    public function testRecordsAutomaticallyWhenCassetteMissing()
    {
        $realClient = new MockHttpClient(new JsonMockResponse(['foo' => 'bar']));
        $client = new CassetteHttpClient(new HttpCassette($this->path), $realClient);

        $this->assertSame(['foo' => 'bar'], $client->request('GET', 'https://example.com')->toArray());
        $this->assertFileExists($this->path);
    }

    public function testAwsCredentialRequestsBypassRecording()
    {
        $realClient = new MockHttpClient([
            new JsonMockResponse(['roleCredentials' => ['accessKeyId' => 'AKIDEXAMPLE', 'secretAccessKey' => 'secret']]),
            new JsonMockResponse(['answer' => 'recorded']),
        ]);
        $client = new CassetteHttpClient(new HttpCassette($this->path), $realClient, record: true);

        $credentials = $client->request('GET', 'https://portal.sso.eu-central-1.amazonaws.com/federation/credentials')->toArray();
        $response = $client->request('POST', 'https://bedrock-mantle.eu-central-1.api.aws/anthropic/v1/messages')->toArray();

        $this->assertSame('AKIDEXAMPLE', $credentials['roleCredentials']['accessKeyId']);
        $this->assertSame(['answer' => 'recorded'], $response);

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['interactions']);
        $this->assertSame('https://bedrock-mantle.eu-central-1.api.aws/anthropic/v1/messages', $data['interactions'][0]['request']['url']);
        $this->assertStringNotContainsString('AKIDEXAMPLE', (string) file_get_contents($this->path));
        $this->assertStringNotContainsString('secret', (string) file_get_contents($this->path));
    }

    public function testReplaysAutomaticallyWhenCassetteExists()
    {
        $recorder = new HttpCassette($this->path);
        $recorder->record('GET', 'https://example.com', [], 200, ['content-type' => ['application/json']], '{"n":1}');

        // no real client given: replay is auto-detected because the cassette exists
        $client = new CassetteHttpClient(new HttpCassette($this->path));

        $this->assertSame(['n' => 1], $client->request('GET', 'https://example.com')->toArray());
    }

    public function testReplaysRecordedResponsesInOrder()
    {
        $recorder = new HttpCassette($this->path);
        $recorder->record('POST', 'https://example.com', [], 200, ['content-type' => ['application/json']], '{"n":1}');
        $recorder->record('POST', 'https://example.com', [], 200, ['content-type' => ['application/json']], '{"n":2}');

        $client = new CassetteHttpClient(new HttpCassette($this->path), record: false);

        $this->assertSame(['n' => 1], $client->request('POST', 'https://example.com')->toArray());
        $this->assertSame(['n' => 2], $client->request('POST', 'https://example.com')->toArray());
    }

    public function testReplayThrowsWhenCassetteExhausted()
    {
        $recorder = new HttpCassette($this->path);
        $recorder->record('GET', 'https://example.com', [], 200, [], 'only');

        $client = new CassetteHttpClient(new HttpCassette($this->path), record: false);
        $client->request('GET', 'https://example.com')->getContent();

        $this->expectException(RuntimeException::class);
        $client->request('GET', 'https://example.com')->getContent();
    }

    public function testRecordRequiresRealClient()
    {
        $this->expectException(InvalidArgumentException::class);
        new CassetteHttpClient(new HttpCassette($this->path), null, record: true);
    }

    public function testReplaysServerSentEventStream()
    {
        $sse = "data: {\"delta\": \"Hel\"}\n\ndata: {\"delta\": \"lo\"}\n\n";
        $recorder = new HttpCassette($this->path);
        $recorder->record('POST', 'https://example.com', [], 200, ['content-type' => ['text/event-stream']], $sse);

        $client = new CassetteHttpClient(new HttpCassette($this->path), record: false);
        $response = (new EventSourceHttpClient($client))->request('POST', 'https://example.com');

        $deltas = iterator_to_array((new RawHttpResult($response, new SseStream()))->getDataStream());

        $this->assertSame([['delta' => 'Hel'], ['delta' => 'lo']], $deltas);
    }

    public function testRecordsStreamAsServerSentEventsAndReplaysItWithoutContentType()
    {
        $sse = "data: {\"delta\": \"Hel\"}\n\ndata: {\"delta\": \"lo\"}\n\n";

        // A provider may omit the content type; the body shape still identifies the framing.
        $realClient = new MockHttpClient(new MockResponse($sse));
        $recording = new CassetteHttpClient(new HttpCassette($this->path), $realClient, record: true);
        $recording->request('POST', 'https://example.com')->getContent();

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('sse', $data['interactions'][0]['response']['body_format']);

        $client = new CassetteHttpClient(new HttpCassette($this->path), record: false);
        $response = (new EventSourceHttpClient($client))->request('POST', 'https://example.com');

        $deltas = iterator_to_array((new RawHttpResult($response, new SseStream()))->getDataStream());

        $this->assertSame([['delta' => 'Hel'], ['delta' => 'lo']], $deltas);
    }

    public function testRecordsBinaryResponseAsMetadataStubAndReplaysPlaceholder()
    {
        $bytes = "\x00\x01\xff\xfeRIFF-fake-audio-bytes";

        $realClient = new MockHttpClient(new MockResponse($bytes, ['response_headers' => ['content-type' => 'audio/mpeg']]));
        $recording = new CassetteHttpClient(new HttpCassette($this->path), $realClient, record: true);

        // During recording the caller still receives the real bytes.
        $this->assertSame($bytes, $recording->request('GET', 'https://example.com/speech')->getContent());

        // The cassette keeps only the metadata stub, never the binary body.
        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('binary', $data['interactions'][0]['response']['body_format']);
        $this->assertNull($data['interactions'][0]['response']['body']);
        $this->assertSame(\strlen($bytes), $data['interactions'][0]['response']['body_size']);
        $this->assertSame(['audio/mpeg'], $data['interactions'][0]['response']['headers']['content-type']);

        // Replay serves a small, diffable placeholder body with the recorded metadata.
        $client = new CassetteHttpClient(new HttpCassette($this->path), record: false);
        $response = $client->request('GET', 'https://example.com/speech');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['audio/mpeg'], $response->getHeaders()['content-type']);
        $this->assertSame(\sprintf('[%d bytes of binary body elided by the cassette]', \strlen($bytes)), $response->getContent());
    }

    public function testDetectsBinaryBodyWithoutContentType()
    {
        $realClient = new MockHttpClient(new MockResponse("plain-prefix-then\x00binary"));
        $recording = new CassetteHttpClient(new HttpCassette($this->path), $realClient, record: true);
        $recording->request('GET', 'https://example.com')->getContent();

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('binary', $data['interactions'][0]['response']['body_format']);
        $this->assertNull($data['interactions'][0]['response']['body']);
    }

    public function testReplayDropsRecordedTransferHeaders()
    {
        $recorder = new HttpCassette($this->path);
        $recorder->record('GET', 'https://example.com', [], 200, [
            'content-type' => ['application/json'],
            'content-length' => ['999'],
            'content-encoding' => ['gzip'],
        ], '{"ok":true}');

        $client = new CassetteHttpClient(new HttpCassette($this->path), record: false);
        $response = $client->request('GET', 'https://example.com');

        $this->assertSame(['ok' => true], $response->toArray());
        $this->assertArrayNotHasKey('content-length', $response->getHeaders());
        $this->assertArrayNotHasKey('content-encoding', $response->getHeaders());
    }

    public function testWithOptionsReturnsWorkingClient()
    {
        $recorder = new HttpCassette($this->path);
        $recorder->record('GET', 'https://example.com', [], 200, ['content-type' => ['application/json']], '{"ok":true}');

        $client = (new CassetteHttpClient(new HttpCassette($this->path), record: false))->withOptions(['base_uri' => 'https://example.com']);

        $this->assertInstanceOf(CassetteHttpClient::class, $client);
        $this->assertSame(['ok' => true], $client->request('GET', 'https://example.com')->toArray());
    }
}
