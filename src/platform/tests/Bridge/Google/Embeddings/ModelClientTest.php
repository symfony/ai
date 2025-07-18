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
use Symfony\AI\Platform\Bridge\Google\Embeddings\ModelClient;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(ModelClient::class)]
#[Small]
#[UsesClass(Vector::class)]
#[UsesClass(VectorResult::class)]
#[UsesClass(Embeddings::class)]
final class ModelClientTest extends TestCase
{
    #[Test]
    public function itMakesARequestWithCorrectPayload(): void
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(json_decode($this->getEmbeddingStub(), true));

        $httpClient = self::createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-exp-03-07:batchEmbedContents',
                [
                    'headers' => ['x-goog-api-key' => 'test'],
                    'json' => [
                        'requests' => [
                            [
                                'model' => 'models/gemini-embedding-exp-03-07',
                                'content' => ['parts' => [['text' => 'payload1']]],
                                'outputDimensionality' => 1536,
                                'taskType' => 'CLASSIFICATION',
                            ],
                            [
                                'model' => 'models/gemini-embedding-exp-03-07',
                                'content' => ['parts' => [['text' => 'payload2']]],
                                'outputDimensionality' => 1536,
                                'taskType' => 'CLASSIFICATION',
                            ],
                        ],
                    ],
                ],
            )
            ->willReturn($result);

        $model = new Embeddings(Embeddings::GEMINI_EMBEDDING_EXP_03_07, ['dimensions' => 1536, 'task_type' => 'CLASSIFICATION']);

        $result = (new ModelClient($httpClient, 'test'))->request($model, ['payload1', 'payload2']);
        self::assertSame(json_decode($this->getEmbeddingStub(), true), $result->getData());
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
