<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Google\Embeddings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Google\Embeddings;
use Symfony\AI\Platform\Bridge\Google\Embeddings\ResponseConverter;
use Symfony\AI\Platform\Response\VectorResponse;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(ResponseConverter::class)]
#[Small]
#[UsesClass(Vector::class)]
#[UsesClass(VectorResponse::class)]
#[UsesClass(Embeddings::class)]
final class ResponseConverterTest extends TestCase
{
    #[Test]
    public function itConvertsAResponseToAVectorResponse(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('getContent')
            ->willReturn($this->getEmbeddingStub());

        $vectorResponse = (new ResponseConverter())->convert($response);
        $convertedContent = $vectorResponse->getContent();

        self::assertCount(2, $convertedContent);

        self::assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        self::assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    private function getEmbeddingStub(): string
    {
        return <<<'JSON'
            {
              "embeddings": [
                {
                  "values": [0.3, 0.4, 0.4]
                },
                {
                  "values": [0.0, 0.0, 0.2]
                }
              ]
            }
            JSON;
    }
}
