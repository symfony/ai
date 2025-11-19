<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Voyage;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\ResultConverter;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class ResultConverterTest extends TestCase
{
    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn([
                'data' => [
                    [
                        'embedding' => [0.1, 0.2, 0.3],
                    ],
                ],
            ]);

        $converter = new ResultConverter();
        $vectorResult = $converter->convert(new RawHttpResult($result));

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
    }

    public function testItConvertsMultipleEmbeddings()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn([
                'data' => [
                    [
                        'embedding' => [0.1, 0.2, 0.3],
                    ],
                    [
                        'embedding' => [0.4, 0.5, 0.6],
                    ],
                ],
            ]);

        $converter = new ResultConverter();
        $vectorResult = $converter->convert(new RawHttpResult($result));

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(2, $vectorResult->getContent());
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
        $this->assertSame([0.4, 0.5, 0.6], $vectorResult->getContent()[1]->getData());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainData()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(['invalid' => 'response']);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embedding data.');

        $converter->convert(new RawHttpResult($result));
    }

    public function testItSupportsVoyageModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Voyage('voyage-3-5')));
    }
}
