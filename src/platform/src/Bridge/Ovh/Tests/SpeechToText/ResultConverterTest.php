<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\SpeechToText;
use Symfony\AI\Platform\Bridge\Ovh\SpeechToText\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsSpeechToTextModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new SpeechToText('whisper-large-v3')));
        $this->assertFalse($converter->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItConvertsResponseToTextResult()
    {
        $result = (new ResultConverter())->convert($this->createResult('{"text": "Hello, this is a transcription test."}'));

        $this->assertSame('Hello, this is a transcription test.', $result->getContent());
    }

    public function testItAddsUsageMetadataWhenPresent()
    {
        $result = (new ResultConverter())->convert($this->createResult('{"text": "Hello.", "usage": {"type": "duration", "seconds": 12}}'));

        $this->assertSame(['type' => 'duration', 'seconds' => 12], $result->getMetadata()->get('usage'));
    }

    public function testItReturnsThePlainBodyForTheTextResponseFormat()
    {
        $result = (new ResultConverter())->convert($this->createResult('Hello, this is a transcription test.'), ['response_format' => 'text']);

        $this->assertSame('Hello, this is a transcription test.', $result->getContent());
    }

    public function testItThrowsExceptionOnServerError()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        (new ResultConverter())->convert($this->createResult('Internal Server Error', 500));
    }

    public function testItThrowsExceptionOnUnexpectedStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 204');

        (new ResultConverter())->convert($this->createResult('', 204));
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainText()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain transcription text.');

        (new ResultConverter())->convert($this->createResult('{"invalid": "response"}'));
    }

    public function testItHasNoTokenUsageExtractor()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }

    private function createResult(string $body, int $status = 200): RawHttpResult
    {
        $response = (new MockHttpClient(new MockResponse($body, ['http_code' => $status])))->request('POST', 'https://example.com/');

        return new RawHttpResult($response);
    }
}
