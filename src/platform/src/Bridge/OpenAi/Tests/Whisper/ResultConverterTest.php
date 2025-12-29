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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;

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
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello, this is a transcription.',
        ]);

        $result = $this->resultConverter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription.', $result->getContent());
    }

    public function testConvertNonVerboseResultWithVerboseOptionFalse()
    {
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello, this is a transcription.',
        ]);

        $result = $this->resultConverter->convert($rawResult, ['verbose' => false]);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription.', $result->getContent());
    }

    public function testConvertNonVerboseResultWithUsage()
    {
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello, this is a transcription.',
            'usage' => ['type' => 'duration', 'duration' => 3],
        ]);

        $result = $this->resultConverter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame(['type' => 'duration', 'duration' => 3], $result->getMetadata()->get('usage'));
    }

    public function testConvertVerboseResult()
    {
        $rawResult = new InMemoryRawResult([
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
        $rawResult = new InMemoryRawResult([
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
        $rawResult = new InMemoryRawResult([
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
        $rawResult = new InMemoryRawResult([
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
        $rawResult = new InMemoryRawResult([
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
        $rawResult = new InMemoryRawResult([
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
}
