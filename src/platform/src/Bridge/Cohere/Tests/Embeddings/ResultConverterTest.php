<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsEmbeddingsModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Embeddings('embed-english-v3.0')));
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

    public function testItConvertsAResponseToAVectorResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'embeddings' => [
                'float' => [
                    [0.1, 0.2, 0.3],
                ],
            ],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
    }

    public function testItConvertsMultipleEmbeddings()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'embeddings' => [
                'float' => [
                    [0.1, 0.2, 0.3],
                    [0.4, 0.5, 0.6],
                ],
            ],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
        $this->assertSame([0.4, 0.5, 0.6], $result->getContent()[1]->getData());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainData()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embedding data.');

        $converter->convert(new RawHttpResult($response));
    }
}
