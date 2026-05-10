<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Whisper;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Segment;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Transcript;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    private ResultConverter $resultConverter;

    protected function setUp(): void
    {
        $this->resultConverter = new ResultConverter();
    }

    public function testSupportsWhisperModel()
    {
        $this->assertTrue($this->resultConverter->supports(new Whisper('whisper-1')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $this->assertFalse($this->resultConverter->supports(new Model('generic-model')));
    }

    public function testConvertNonVerboseResult()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, this is a transcription.',
        ]);

        $result = $this->resultConverter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription.', $result->getContent());
    }

    public function testConvertNonVerboseResultWithVerboseOptionFalse()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, this is a transcription.',
        ]);

        $result = $this->resultConverter->convert($rawResult, ['verbose' => false]);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription.', $result->getContent());
    }

    public function testConvertNonVerboseResultWithUsage()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, this is a transcription.',
            'usage' => ['type' => 'duration', 'duration' => 3],
        ]);

        $result = $this->resultConverter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame(['type' => 'duration', 'duration' => 3], $result->getMetadata()->get('usage'));
    }

    public function testConvertVerboseResult()
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

        $result = $this->resultConverter->convert($rawResult, ['verbose' => true]);

        $this->assertInstanceOf(ObjectResult::class, $result);

        $transcript = $result->getContent();
        $this->assertInstanceOf(Transcript::class, $transcript);
        $this->assertSame('Hello, world!', $transcript->getText());
        $this->assertSame('en', $transcript->getLanguage());
        $this->assertSame(5.5, $transcript->getDuration());

        $segments = $transcript->getSegments();
        $this->assertCount(2, $segments);

        $this->assertInstanceOf(Segment::class, $segments[0]);
        $this->assertSame(0.0, $segments[0]->getStart());
        $this->assertSame(2.5, $segments[0]->getEnd());
        $this->assertSame('Hello,', $segments[0]->getText());

        $this->assertInstanceOf(Segment::class, $segments[1]);
        $this->assertSame(2.5, $segments[1]->getStart());
        $this->assertSame(5.5, $segments[1]->getEnd());
        $this->assertSame(' world!', $segments[1]->getText());
    }

    public function testConvertVerboseResultWithUsage()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'language' => 'en',
            'duration' => 1.0,
            'segments' => [],
            'usage' => ['type' => 'duration', 'duration' => 3],
        ]);

        $result = $this->resultConverter->convert($rawResult, ['verbose' => true]);

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertSame(['type' => 'duration', 'duration' => 3], $result->getMetadata()->get('usage'));
    }

    public function testVerboseResultThrowsExceptionWhenMissingText()
    {
        $rawResult = $this->createRawResult([
            'language' => 'en',
            'duration' => 5.5,
            'segments' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testVerboseResultThrowsExceptionWhenMissingLanguage()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'duration' => 5.5,
            'segments' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testVerboseResultThrowsExceptionWhenMissingDuration()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'language' => 'en',
            'segments' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testVerboseResultThrowsExceptionWhenMissingSegments()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'language' => 'en',
            'duration' => 5.5,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testGetTokenUsageExtractorReturnsNull()
    {
        $this->assertNull($this->resultConverter->getTokenUsageExtractor());
    }

    public function testThrowsAuthenticationExceptionOn401()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Invalid API key provided: sk-invalid',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided: sk-invalid');

        $this->resultConverter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOn400()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Invalid file format.',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid file format.');

        $this->resultConverter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOn400WithNoMessage()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn('{}');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $this->resultConverter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsRateLimitExceededExceptionOn429()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(429);
        $httpResponse->method('toArray')->willReturn([]);
        $httpResponse->method('getContent')->willReturn('{"error":{"message":"You exceeded your current quota, please check your plan and billing details."}}');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. You exceeded your current quota, please check your plan and billing details.');

        $this->resultConverter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsContentFilterException()
    {
        $rawResult = $this->createRawResult([
            'error' => [
                'code' => 'content_filter',
                'message' => 'Content was filtered due to policy violation.',
            ],
        ]);

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content was filtered due to policy violation.');

        $this->resultConverter->convert($rawResult);
    }

    public function testThrowsRuntimeExceptionOnGenericError()
    {
        $rawResult = $this->createRawResult([
            'error' => [
                'code' => 'server_error',
                'type' => 'internal',
                'param' => null,
                'message' => 'Something went wrong',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "server_error"-internal (-): "Something went wrong".');

        $this->resultConverter->convert($rawResult);
    }

    public function testThrowsRuntimeExceptionWhenTextFieldMissing()
    {
        $rawResult = $this->createRawResult([
            'task' => 'transcribe',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The response is missing the required "text" field.');

        $this->resultConverter->convert($rawResult);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn($statusCode);
        $httpResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($httpResponse);
    }
}
