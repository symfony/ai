<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Mock\Recording;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Mock\Recording\ResultSerializer;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

final class ResultSerializerTest extends TestCase
{
    public function testTextRoundTrip()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new TextResult('Hello', 'sig-1')));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello', $result->getContent());
        $this->assertSame('sig-1', $result->getSignature());
    }

    public function testObjectRoundTripWithObject()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new ObjectResult((object) ['answer' => 42])));

        $this->assertInstanceOf(ObjectResult::class, $result);
        $content = $result->getContent();
        $this->assertIsObject($content);
        $this->assertSame(42, $content->answer);
    }

    public function testObjectRoundTripWithArray()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new ObjectResult(['answer' => 42])));

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertSame(['answer' => 42], $result->getContent());
    }

    public function testVectorRoundTrip()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new VectorResult([new Vector([0.1, 0.2, 0.3])])));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
    }

    public function testToolCallRoundTrip()
    {
        $result = ResultSerializer::fromArray(ResultSerializer::toArray(new ToolCallResult([
            new ToolCall('id-1', 'get_weather', ['location' => 'Paris'], 'sig-2'),
        ])));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCall = $result->getContent()[0];
        $this->assertSame('id-1', $toolCall->getId());
        $this->assertSame('get_weather', $toolCall->getName());
        $this->assertSame(['location' => 'Paris'], $toolCall->getArguments());
        $this->assertSame('sig-2', $toolCall->getSignature());
    }

    public function testTextStreamRoundTrip()
    {
        $stream = new StreamResult((static function (): \Generator {
            yield new TextDelta('Hel');
            yield new TextDelta('lo');
        })());

        $result = ResultSerializer::fromArray(ResultSerializer::toArray($stream));

        $this->assertInstanceOf(StreamResult::class, $result);

        $text = '';
        foreach ($result->getContent() as $delta) {
            $this->assertInstanceOf(TextDelta::class, $delta);
            $text .= $delta->getText();
        }

        $this->assertSame('Hello', $text);
    }

    public function testUnsupportedResultTypeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        ResultSerializer::toArray(new BinaryResult('binary-data'));
    }

    public function testUnsupportedStreamDeltaThrows()
    {
        $stream = new StreamResult((static function (): \Generator {
            yield new ThinkingDelta('reasoning');
        })());

        $this->expectException(InvalidArgumentException::class);
        ResultSerializer::toArray($stream);
    }

    public function testUnknownTypeOnFromArrayThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        ResultSerializer::fromArray(['type' => 'unknown']);
    }
}
