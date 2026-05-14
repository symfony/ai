<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Speech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Speech\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenRouter\SpeechModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsSpeechModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new SpeechModel('openai/gpt-4o-mini-tts-2025-12-15')));
    }

    public function testItDoesNotSupportNonSpeechModel()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new CompletionsModel('openrouter/auto')));
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

    public function testItConvertsResponseToBinaryResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('fake-audio-bytes');

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('fake-audio-bytes', $result->getContent());
    }

    public function testItHasNoTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertNull($converter->getTokenUsageExtractor());
    }
}
