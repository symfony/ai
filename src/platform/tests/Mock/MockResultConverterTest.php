<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Mock;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Mock\MockResultConverter;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

final class MockResultConverterTest extends TestCase
{
    public function testSupportsReturnsTrueForAnyModel()
    {
        $converter = new MockResultConverter();

        $this->assertTrue($converter->supports(new Model('any-model')));
    }

    public function testGetTokenUsageExtractorReturnsNull()
    {
        $converter = new MockResultConverter();

        $this->assertNull($converter->getTokenUsageExtractor());
    }

    /**
     * @return iterable<string, array{ResultInterface}>
     */
    public static function provideResultTypes(): iterable
    {
        yield 'object' => [new ObjectResult(['foo' => 'bar'])];
        yield 'stream' => [new StreamResult((static function (): \Generator { yield new TextDelta('delta'); })())];
        yield 'tool-call' => [new ToolCallResult([new ToolCall('id-1', 'my_tool')])];
        yield 'vector' => [new VectorResult([new Vector([0.1, 0.2, 0.3])])];
    }

    #[DataProvider('provideResultTypes')]
    public function testConvertPassesResultThroughUnchanged(ResultInterface $result)
    {
        $converter = new MockResultConverter();

        $converted = $converter->convert(new InMemoryRawResult(object: $result));

        $this->assertSame($result, $converted);
    }

    public function testConvertFallsBackToTextResultFromData()
    {
        $converter = new MockResultConverter();

        $converted = $converter->convert(new InMemoryRawResult(['text' => 'plain']));

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame('plain', $converted->getContent());
    }

    public function testConvertFallsBackToEmptyStringWhenNoTextData()
    {
        $converter = new MockResultConverter();

        $converted = $converter->convert(new InMemoryRawResult());

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame('', $converted->getContent());
    }
}
