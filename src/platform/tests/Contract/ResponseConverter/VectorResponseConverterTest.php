<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\ResponseConverter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\ResponseConverter\VectorResponseConverter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(VectorResponseConverter::class)]
final class VectorResponseConverterTest extends TestCase
{
    #[Test]
    public function standardSuccess(): void
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/standard-embeddings-success.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/embeddings');

        $converter = new VectorResponseConverter();

        $actual = $converter->convert($response);
        self::assertCount(1, $actual->getContent());
        self::assertSame(5, $actual->getContent()[0]->getDimensions());
    }

    #[Test]
    public function specificSuccess(): void
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/specific-embeddings-success.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/embeddings');

        $converter = new VectorResponseConverter('$.embeddings[*].values');

        $actual = $converter->convert($response);
        self::assertCount(1, $actual->getContent());
        self::assertSame(6, $actual->getContent()[0]->getDimensions());
    }

    /**
     * This can be replaced by `JsonMockResponse::fromFile` when dropping Symfony 6.4.
     */
    private function jsonMockResponseFromFile(string $file): JsonMockResponse
    {
        return new JsonMockResponse(json_decode(file_get_contents($file), true));
    }
}
