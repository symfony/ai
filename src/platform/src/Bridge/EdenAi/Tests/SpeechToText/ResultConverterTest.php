<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsSpeechToTextModelOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new SpeechToText('audio/speech_to_text_async/openai')));
        $this->assertFalse($converter->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItConvertsResponseToTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'success',
            'provider' => 'deepgram',
            'cost' => '0.0008',
            'output' => [
                'text' => 'Hello world',
                'diarization' => [
                    'total_speakers' => 1,
                    'entries' => [
                        ['segment' => 'Hello world', 'speaker' => 1],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
        $this->assertSame('deepgram', $result->getMetadata()->get('provider'));
        $this->assertSame(0.0008, $result->getMetadata()->get('cost'));
        $this->assertSame(1, $result->getMetadata()->get('diarization')['total_speakers']);
    }

    public function testItThrowsWhenJobFails()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'failed',
            'output' => null,
            'error' => ['message' => 'Audio could not be processed.'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Audio could not be processed."');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testItThrowsWhenTextIsMissing()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse(['status' => 'success', 'output' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain text.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data): ResponseInterface
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn($data);

        return $httpResponse;
    }
}
