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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\ModelClient;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    #[DataProvider('requestProvider')]
    public function testItSendsExpectedRequest(Voyage $model, string $expectedPath, array $expectedPayload)
    {
        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ) use ($expectedPath, $expectedPayload): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame(\sprintf('https://api.voyageai.com/v1/%s', $expectedPath), $url);
            self::assertSame(json_encode($expectedPayload), $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, '');

        $client->request($model, 'Hello, world!', [
            'dimensions' => 300,
        ]);
    }

    public static function requestProvider(): \Generator
    {
        $textEmbeddingModel = new Voyage('some-text-embedding-model', []);
        $multimodalEmbeddingModel = new Voyage('some-multimodal-embedding-model', [Capability::INPUT_MULTIMODAL]);
        $input = 'Hello, world!';

        yield 'for text embedding' => [
            $textEmbeddingModel,
            'embeddings',
            [
                'model' => $textEmbeddingModel->getName(),
                'input' => $input,
                'input_type' => null,
                'truncation' => true,
                'output_dimension' => 300,
                'encoding_format' => null,
            ],
        ];

        yield 'for multimodal embedding' => [
            $multimodalEmbeddingModel,
            'multimodalembeddings',
            [
                'model' => $multimodalEmbeddingModel->getName(),
                'inputs' => $input,
                'input_type' => null,
                'truncation' => true,
                'output_encoding' => null,
            ],
        ];
    }
}
