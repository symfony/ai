<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result\Stream;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\Stream\NdjsonStream;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class NdjsonStreamTest extends TestCase
{
    public function testStreamWithSingleLine()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'hello', 'done' => true])."\n"),
        ]);
        $response = $httpClient->request('GET', 'https://example.com');

        $stream = new NdjsonStream($httpClient);
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(1, $results);
        $this->assertSame(['message' => 'hello', 'done' => true], $results[0]);
    }

    public function testStreamWithMultipleLines()
    {
        $ndjson = json_encode(['model' => 'llama3.2', 'message' => ['content' => 'Hello'], 'done' => false])."\n"
            .json_encode(['model' => 'llama3.2', 'message' => ['content' => ' world'], 'done' => false])."\n"
            .json_encode(['model' => 'llama3.2', 'message' => ['content' => ''], 'done' => true])."\n";

        $httpClient = new MockHttpClient([
            new MockResponse($ndjson),
        ]);
        $response = $httpClient->request('GET', 'https://example.com');

        $stream = new NdjsonStream($httpClient);
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(3, $results);
        $this->assertSame('Hello', $results[0]['message']['content']);
        $this->assertFalse($results[0]['done']);
        $this->assertSame(' world', $results[1]['message']['content']);
        $this->assertFalse($results[1]['done']);
        $this->assertSame('', $results[2]['message']['content']);
        $this->assertTrue($results[2]['done']);
    }

    public function testStreamHandlesChunkBoundaryMidObject()
    {
        $line1 = json_encode(['model' => 'llama3.2', 'message' => ['content' => 'Hello'], 'done' => false]);
        $line2 = json_encode(['model' => 'llama3.2', 'message' => ['content' => ' world'], 'done' => true]);

        $splitPoint = (int) (\strlen($line1) / 2);
        $chunk1 = substr($line1, 0, $splitPoint);
        $chunk2 = substr($line1, $splitPoint)."\n".$line2."\n";

        $httpClient = new MockHttpClient([
            new MockResponse([$chunk1, $chunk2]),
        ]);
        $response = $httpClient->request('GET', 'https://example.com');

        $stream = new NdjsonStream($httpClient);
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(2, $results);
        $this->assertSame('Hello', $results[0]['message']['content']);
        $this->assertFalse($results[0]['done']);
        $this->assertSame(' world', $results[1]['message']['content']);
        $this->assertTrue($results[1]['done']);
    }

    public function testStreamIgnoresEmptyLines()
    {
        $ndjson = json_encode(['done' => false])."\n\n\n".json_encode(['done' => true])."\n";

        $httpClient = new MockHttpClient([
            new MockResponse($ndjson),
        ]);
        $response = $httpClient->request('GET', 'https://example.com');

        $stream = new NdjsonStream($httpClient);
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]['done']);
        $this->assertTrue($results[1]['done']);
    }

    public function testStreamHandlesLastLineWithoutTrailingNewline()
    {
        $ndjson = json_encode(['message' => 'hello', 'done' => true]);

        $httpClient = new MockHttpClient([
            new MockResponse($ndjson),
        ]);
        $response = $httpClient->request('GET', 'https://example.com');

        $stream = new NdjsonStream($httpClient);
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(1, $results);
        $this->assertSame('hello', $results[0]['message']);
    }
}
