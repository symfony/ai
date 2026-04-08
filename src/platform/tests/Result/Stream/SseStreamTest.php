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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class SseStreamTest extends TestCase
{
    public function testStream()
    {
        $sse = "data: {\"foo\": \"bar\"}\n\n";
        $response = new MockResponse($sse);
        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $stream = new SseStream($httpClient);
        $results = iterator_to_array($stream->stream($actualResponse));

        $this->assertCount(1, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
    }

    public function testStreamHandlesDoneEvent()
    {
        $sse = "data: {\"foo\": \"bar\"}\n\ndata: [DONE]\n\n";
        $response = new MockResponse($sse);
        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $stream = new SseStream($httpClient);
        $results = iterator_to_array($stream->stream($actualResponse));

        $this->assertCount(1, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
    }

    public function testStreamHandlesEmptyColonInResponseAsCommentAndIgnore()
    {
        $sse = ": OPENROUTER PROCESSING\n\ndata: {\"foo\": \"bar\"}\n\n";
        $response = new MockResponse($sse);
        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $stream = new SseStream($httpClient);
        $results = iterator_to_array($stream->stream($actualResponse));

        $this->assertCount(1, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
    }

    public function testStreamHandlesBracketsAndCommas()
    {
        $sse = "data: [{\"foo\": \"bar\"}]\n\ndata: ,{\"baz\": \"qux\"}\n\n";
        $response = new MockResponse($sse);
        $httpClient = new MockHttpClient([$response]);
        $actualResponse = $httpClient->request('GET', 'https://example.com');

        $stream = new SseStream($httpClient);
        $results = iterator_to_array($stream->stream($actualResponse));

        $this->assertCount(2, $results);
        $this->assertSame(['foo' => 'bar'], $results[0]);
        $this->assertSame(['baz' => 'qux'], $results[1]);
    }
}
