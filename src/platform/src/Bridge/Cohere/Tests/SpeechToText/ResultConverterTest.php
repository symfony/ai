<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\SpeechToText;
use Symfony\AI\Platform\Bridge\Cohere\SpeechToText\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsSpeechToTextModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new SpeechToText('cohere-transcribe-03-2026')));
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('Internal Server Error');

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 500');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItConvertsResponseToTextResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'text' => 'Hello, this is a transcription test.',
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription test.', $result->getContent());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainText()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain transcription text.');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItReturnsNullTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertNull($converter->getTokenUsageExtractor());
    }
}
