<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests\Audio;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\Result\Transcript;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionModel;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionResultConverter;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TranscriptionResultConverterTest extends TestCase
{
    public function testItSupportsTranscriptionModelOnly()
    {
        $converter = new TranscriptionResultConverter();

        $this->assertTrue($converter->supports(new TranscriptionModel('openai/whisper-1')));
        $this->assertFalse($converter->supports(new Model('test-model')));
    }

    public function testItConvertsSimpleTranscription()
    {
        $rawResult = $this->createRawResult(['text' => 'Hello, this is a transcription.']);

        $result = (new TranscriptionResultConverter())->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription.', $result->getContent());
    }

    public function testItPropagatesUsageMetadata()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'usage' => ['type' => 'duration', 'duration' => 3],
        ]);

        $result = (new TranscriptionResultConverter())->convert($rawResult);

        $this->assertSame(['type' => 'duration', 'duration' => 3], $result->getMetadata()->get('usage'));
    }

    public function testItConvertsVerboseTranscription()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, world!',
            'language' => 'en',
            'duration' => 5.5,
            'segments' => [
                ['start' => 0.0, 'end' => 2.5, 'text' => 'Hello,'],
                ['start' => 2.5, 'end' => 5.5, 'text' => ' world!'],
            ],
        ]);

        $result = (new TranscriptionResultConverter())->convert($rawResult, ['verbose' => true]);

        $this->assertInstanceOf(ObjectResult::class, $result);
        $transcript = $result->getContent();
        $this->assertInstanceOf(Transcript::class, $transcript);
        $this->assertSame('Hello, world!', $transcript->getText());
        $this->assertSame('en', $transcript->getLanguage());
        $this->assertSame(5.5, $transcript->getDuration());
        $this->assertCount(2, $transcript->getSegments());
    }

    public function testItThrowsOnMissingTextField()
    {
        $rawResult = $this->createRawResult(['language' => 'en']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The response is missing the required "text" field.');

        (new TranscriptionResultConverter())->convert($rawResult);
    }

    public function testItThrowsOnIncompleteVerboseResponse()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'language' => 'en',
            'segments' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        (new TranscriptionResultConverter())->convert($rawResult, ['verbose' => true]);
    }

    public function testItThrowsBadRequestOn400()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('toArray')->willReturn(['error' => ['message' => 'Invalid audio file.']]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid audio file.');

        (new TranscriptionResultConverter())->convert(new RawHttpResult($response));
    }

    public function testItThrowsRuntimeExceptionOnPayloadError()
    {
        $rawResult = $this->createRawResult([
            'error' => [
                'code' => 'unknown_model',
                'type' => 'invalid_request_error',
                'param' => 'model',
                'message' => 'Unknown model identifier.',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "unknown_model"-invalid_request_error (model): "Unknown model identifier."');

        (new TranscriptionResultConverter())->convert($rawResult);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data): RawHttpResult
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn($data);

        return new RawHttpResult($response);
    }
}
