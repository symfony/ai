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
use Symfony\AI\Platform\Result\Stream\SseStream;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class SseStreamTest extends TestCase
{
    public function testStream()
    {
        $sse = "data: {\"foo\": \"bar\"}\n\n";
        $response = $this->createResponse($sse);

        $stream = new SseStream();
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(1, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
    }

    public function testStreamHandlesDoneEvent()
    {
        $sse = "data: {\"foo\": \"bar\"}\n\ndata: [DONE]\n\n";
        $response = $this->createResponse($sse);

        $stream = new SseStream();
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(1, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
    }

    public function testStreamHandlesEmptyColonInResponseAsCommentAndIgnore()
    {
        $sse = ": OPENROUTER PROCESSING\n\ndata: {\"foo\": \"bar\"}\n\n";
        $response = $this->createResponse($sse);

        $stream = new SseStream();
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(1, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
    }

    public function testStreamHandlesBracketsAndCommas()
    {
        $sse = "data: [{\"foo\": \"bar\"}]\n\ndata: ,{\"baz\": \"qux\"}\n\n";
        $response = $this->createResponse($sse);

        $stream = new SseStream();
        $results = iterator_to_array($stream->stream($response));

        $this->assertCount(2, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
        $this->assertSame(['baz' => 'qux'], $results[1]);
    }

    private function createResponse(string $body): ResponseInterface
    {
        $mockHttpClient = new MockHttpClient([new MockResponse($body, ['response_headers' => ['content-type' => 'text/event-stream']])]);
        $eventSourceClient = new EventSourceHttpClient($mockHttpClient);

        return $eventSourceClient->request('GET', 'https://example.com');
    }
}
