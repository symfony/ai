<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\ModelClient;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TaskType;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
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
        $this->assertSame(
            'https://aiplatform.googleapis.com/v1/projects/test/locations/global/publishers/google/models/gemini-embedding-001:predict',
            $result->getObject()->getInfo()['url'],
        );
    }

    public function testItUsesTheRegionalEndpointForARegionalLocation()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://europe-west1-aiplatform.googleapis.com/v1/projects/test/locations/europe-west1/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new ModelClient($httpClient, 'europe-west1', 'test');
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItUsesTheResidencyEndpointForAJurisdictionalLocation()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://aiplatform.us.rep.googleapis.com/v1/projects/test/locations/us/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new ModelClient($httpClient, 'us', 'test');
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItUsesTheGlobalHostWhenNoLocationIsProvided()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertStringStartsWith(
                'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new ModelClient($httpClient, apiKey: 'test-key');
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItUsesTheGlobalEndpointWhenTheProjectIdIsMissing()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new ModelClient($httpClient, 'europe-west1');
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItThrowsOnAnInvalidLocation()
    {
        $client = new ModelClient(new MockHttpClient(), 'evil.com/europe-west1', 'test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid location "evil.com/europe-west1". Valid options are "global", "eu", "us", or a region like "europe-west1".');

        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }
}
