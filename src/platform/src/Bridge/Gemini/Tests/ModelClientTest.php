<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\ModelClient;
use Symfony\AI\Platform\Capability;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ModelClientTest extends TestCase
{
    public function testItMakesAnEmbeddingRequestWithCorrectPayload()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(json_decode($this->getEmbeddingStub(), true));

        $expectedJson = [
            'requests' => [
                [
                    'model' => 'models/gemini-embedding-001',
                    'content' => ['parts' => [['text' => 'payload1']]],
                    'outputDimensionality' => 1536,
                    'taskType' => 'CLASSIFICATION',
                ],
                [
                    'model' => 'models/gemini-embedding-001',
                    'content' => ['parts' => [['text' => 'payload2']]],
                    'outputDimensionality' => 1536,
                    'taskType' => 'CLASSIFICATION',
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'models/gemini-embedding-001:batchEmbedContents',
                ['json' => $expectedJson],
            )
            ->willReturn($result);

        $model = new Gemini(
            'gemini-embedding-001',
            [Capability::EMBEDDINGS],
            ['dimensions' => 1536, 'task_type' => 'CLASSIFICATION'],
        );

        $result = (new ModelClient($httpClient))->request($model, ['payload1', 'payload2']);
        $this->assertSame(json_decode($this->getEmbeddingStub(), true), $result->getData());
    }

    public function testItMakesAnAsyncEmbeddingRequest()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'models/gemini-embedding-001:asyncBatchEmbedContent',
                $this->anything(),
            )
            ->willReturn($this->createStub(ResponseInterface::class));

        $model = new Gemini('gemini-embedding-001', [Capability::EMBEDDINGS]);

        (new ModelClient($httpClient))->request($model, ['payload'], ['async' => true]);
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
