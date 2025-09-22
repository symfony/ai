<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\ModelClient;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TaskType;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientTest extends TestCase
{
    public function testItGeneratesTheEmbeddingSuccessfully()
    {
        $expectedResponse = [
            'predictions' => [
                ['embeddings' => ['values' => [0.3, 0.4, 0.4]]],
            ],
        ];
        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));

        $client = new ModelClient($httpClient, 'global', 'test');

        $model = new Model('gemini-embedding-001', options: ['outputDimensionality' => 1536, 'task_type' => TaskType::CLASSIFICATION]);

        $result = $client->request($model, 'test payload');

        $this->assertSame($expectedResponse, $result->getData());
    }
}
