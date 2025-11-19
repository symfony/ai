<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\LmStudio\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LmStudio\Embeddings;
use Symfony\AI\Platform\Bridge\LmStudio\Embeddings\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ResultConverterTest extends TestCase
{
    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(
                json_decode(
                    <<<'JSON'
                        {
                          "object": "list",
                          "data": [
                            {
                              "object": "embedding",
                              "index": 0,
                              "embedding": [0.3, 0.4, 0.4]
                            },
                            {
                              "object": "embedding",
                              "index": 1,
                              "embedding": [0.0, 0.0, 0.2]
                            }
                          ]
                        }
                        JSON,
                    true
                )
            );

        $vectorResult = (new ResultConverter())->convert(new RawHttpResult($result));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainData()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(['invalid' => 'response']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain data');

        (new ResultConverter())->convert(new RawHttpResult($result));
    }

    public function testItSupportsEmbeddingsModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Embeddings('test-model')));
    }
}
