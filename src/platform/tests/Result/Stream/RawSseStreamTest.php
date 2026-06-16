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
use Symfony\AI\Platform\Result\Stream\RawSseStream;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RawSseStreamTest extends TestCase
{
    public function testParsesRegularSseWithEventStreamContentType()
    {
        $body = "data: {\"foo\": \"bar\"}\n\ndata: {\"baz\": \"qux\"}\n\ndata: [DONE]\n\n";
        $response = $this->createResponse($body, 'text/event-stream');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testParsesSseFramingWhenContentTypeIsNotEventStream()
    {
        // The ChatGPT Codex backend streams SSE without advertising
        // "text/event-stream", so the chunks arrive raw and still carry the
        // "event:"/"data:" framing.
        $body = "event: response.output_text.delta\ndata: {\"foo\": \"bar\"}\n\n"
            ."event: response.completed\ndata: {\"baz\": \"qux\"}\n\ndata: [DONE]\n\n";
        $response = $this->createResponse($body, 'application/json');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testParsesSseFramingSplitAcrossChunks()
    {
        $response = $this->createResponse(['data: {"foo": ', "\"bar\"}\n\ndata: {\"baz\": \"qux\"}\n\n"], 'application/json');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testParsesSseFramingWithCrlfLineEndings()
    {
        $body = "event: x\r\ndata: {\"foo\": \"bar\"}\r\n\r\ndata: {\"baz\": \"qux\"}\r\n\r\n";
        $response = $this->createResponse($body, 'application/json');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testParsesSseFramingWithBareCrLineEndings()
    {
        $body = "event: x\rdata: {\"foo\": \"bar\"}\r\rdata: {\"baz\": \"qux\"}\r\r";
        $response = $this->createResponse($body, 'application/json');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testStripsLeadingByteOrderMark()
    {
        $response = $this->createResponse("\xEF\xBB\xBFdata: {\"foo\": \"bar\"}\n\n", 'application/json');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar']], $results);
    }

    public function testFlushesTrailingEventWithoutBlankLineSeparator()
    {
        $response = $this->createResponse('data: {"foo": "bar"}', 'application/json');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar']], $results);
    }

    public function testIgnoresEventsWithoutDataLines()
    {
        $body = ": keep-alive comment\n\nevent: ping\n\ndata: {\"foo\": \"bar\"}\n\n";
        $response = $this->createResponse($body, 'application/json');

        $results = iterator_to_array((new RawSseStream())->stream($response), false);

        $this->assertSame([['foo' => 'bar']], $results);
    }

    /**
     * @param string|list<string> $body
     */
    private function createResponse(string|array $body, string $contentType): ResponseInterface
    {
        $mockHttpClient = new MockHttpClient([new MockResponse($body, ['response_headers' => ['content-type' => $contentType]])]);
        $eventSourceClient = new EventSourceHttpClient($mockHttpClient);

        return $eventSourceClient->request('GET', 'https://example.com');
    }
}
