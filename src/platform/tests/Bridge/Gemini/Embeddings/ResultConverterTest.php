<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Gemini\Embeddings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ResultConverter;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(ResultConverter::class)]
#[Small]
#[UsesClass(Vector::class)]
#[UsesClass(VectorResult::class)]
#[UsesClass(Embeddings::class)]
final class ResultConverterTest extends TestCase
{
    #[Test]
    public function itConvertsAResponseToAVectorResult(): void
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(json_decode($this->getEmbeddingStub(), true));

        $vectorResult = (new ResultConverter())->convert(new RawHttpResult($result));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
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
