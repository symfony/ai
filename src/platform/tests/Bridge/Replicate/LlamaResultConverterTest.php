<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Replicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Replicate\LlamaResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[CoversClass(LlamaResultConverter::class)]
final class LlamaResultConverterTest extends TestCase
{
    public function testSupportsLlamaModel()
    {
        $converter = new LlamaResultConverter();
        $this->assertTrue($converter->supports(new Llama('llama-3.1-405b-instruct')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new LlamaResultConverter();
        $otherModel = $this->createMock(Model::class);
        $this->assertFalse($converter->supports($otherModel));
    }

    public function testConvertWithSingleOutput()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['output' => ['Hello world']]);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertWithMultipleOutputs()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['output' => ['Hello', ' ', 'world', '!']]);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world!', $result->getContent());
    }

    public function testConvertThrowsExceptionWhenOutputMissing()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['status' => 'succeeded']);

        $converter = new LlamaResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output.');

        $converter->convert($rawResult);
    }

    public function testConvertWithEmptyOutput()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['output' => []]);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
    }
}
