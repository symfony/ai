<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ModelClientTest extends TestCase
{
    public function testItMakesARequestWithCorrectPayload()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(json_decode($this->getEmbeddingStub(), true));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
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

        $model = new Embeddings('gemini-embedding-exp-03-07', options: ['dimensions' => 1536, 'task_type' => 'CLASSIFICATION']);

        $result = (new ModelClient($httpClient, 'test'))->request($model, ['payload1', 'payload2']);
        $this->assertSame(json_decode($this->getEmbeddingStub(), true), $result->getData());
    }

    public function testHttpOptionsAreSentWithTheRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame(30.0, $options['max_duration']);
            $this->assertSame([
                'requests' => [
                    ['model' => 'models/gemini-embedding-exp-03-07', 'content' => ['parts' => [['text' => 'payload']]]],
                ],
            ], json_decode($options['body'], true));

            return new MockResponse($this->getEmbeddingStub());
        });

        (new ModelClient($httpClient, 'test'))->request(new Embeddings('gemini-embedding-exp-03-07'), 'payload', ['http_options' => ['max_duration' => 30, 'json' => ['overridden' => true]]]);

        $this->assertSame(1, $httpClient->getRequestsCount());
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
