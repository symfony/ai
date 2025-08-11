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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\ModelClient;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TaskType;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(ModelClient::class)]
#[Small]
#[UsesClass(Vector::class)]
#[UsesClass(VectorResult::class)]
#[UsesClass(Model::class)]
final class ModelClientTest extends TestCase
{
    public function testItGeneratesTheEmbeddingSuccessfully()
    {
        // Assert
        $expectedResponse = [
            'predictions' => [
                ['embeddings' => ['values' => [0.3, 0.4, 0.4]]],
            ],
        ];
        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));

        $client = new ModelClient($httpClient, 'global', 'test');

        $model = new Model(Model::GEMINI_EMBEDDING_001, ['outputDimensionality' => 1536, 'task_type' => TaskType::CLASSIFICATION]);

        // Act
        $result = $client->request($model, 'test payload');

        // Assert
        $this->assertSame($expectedResponse, $result->getData());
    }
}
