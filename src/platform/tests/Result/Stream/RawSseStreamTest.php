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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RawSseStreamTest extends TestCase
{
    public function testParsesHeaderlessSseFraming()
    {
        // Some backends (e.g. the ChatGPT Codex backend) stream SSE without
        // advertising "text/event-stream", so the chunks arrive raw and still
        // carry the "event:"/"data:" framing.
        $body = "event: response.output_text.delta\ndata: {\"foo\": \"bar\"}\n\n"
            ."event: response.completed\ndata: {\"baz\": \"qux\"}\n\ndata: [DONE]\n\n";

        $results = iterator_to_array((new RawSseStream())->stream($this->createResponse($body)));

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testIgnoresCommentLines()
    {
        $body = ": OPENROUTER PROCESSING\n\ndata: {\"foo\": \"bar\"}\n\n";

        $results = iterator_to_array((new RawSseStream())->stream($this->createResponse($body)));

        $this->assertSame([['foo' => 'bar']], $results);
    }

    public function testParsesFramingSplitAcrossChunks()
    {
        $response = $this->createResponse(['data: {"foo": ', "\"bar\"}\n\ndata: {\"baz\": \"qux\"}\n\n"]);

        $results = iterator_to_array((new RawSseStream())->stream($response));

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testParsesCrlfLineEndings()
    {
        $body = "event: x\r\ndata: {\"foo\": \"bar\"}\r\n\r\ndata: {\"baz\": \"qux\"}\r\n\r\n";

        $results = iterator_to_array((new RawSseStream())->stream($this->createResponse($body)));

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testParsesCarriageReturnLineEndings()
    {
        $body = "event: x\rdata: {\"foo\": \"bar\"}\r\rdata: {\"baz\": \"qux\"}\r\r";

        $results = iterator_to_array((new RawSseStream())->stream($this->createResponse($body)));

        $this->assertSame([['foo' => 'bar'], ['baz' => 'qux']], $results);
    }

    public function testParsesLeadingBom()
    {
        $results = iterator_to_array((new RawSseStream())->stream($this->createResponse("\xEF\xBB\xBFdata: {\"foo\": \"bar\"}\n\n")));

        $this->assertSame([['foo' => 'bar']], $results);
    }

    public function testFlushesTrailingEventWithoutBlankLineSeparator()
    {
        $results = iterator_to_array((new RawSseStream())->stream($this->createResponse('data: {"foo": "bar"}')));

        $this->assertSame([['foo' => 'bar']], $results);
    }

    /**
     * @param string|list<string> $body
     */
    private function createResponse(string|array $body): ResponseInterface
    {
        $mockHttpClient = new MockHttpClient([new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']])]);
        $eventSourceClient = new EventSourceHttpClient($mockHttpClient);

        return $eventSourceClient->request('GET', 'https://example.com');
    }
}
