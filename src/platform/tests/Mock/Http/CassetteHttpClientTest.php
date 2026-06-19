<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Mock\Http;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Mock\Http\CassetteHttpClient;
use Symfony\AI\Platform\Mock\Http\HttpCassette;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\SseStream;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

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

    public function testWithOptionsReturnsWorkingClient()
    {
        $recorder = new HttpCassette($this->path);
        $recorder->record('GET', 'https://example.com', [], 200, ['content-type' => ['application/json']], '{"ok":true}');

        $client = (new CassetteHttpClient(new HttpCassette($this->path), record: false))->withOptions(['base_uri' => 'https://example.com']);

        $this->assertInstanceOf(CassetteHttpClient::class, $client);
        $this->assertSame(['ok' => true], $client->request('GET', 'https://example.com')->toArray());
    }
}
