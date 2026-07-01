<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Albert\ApiClient;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiClientTest extends TestCase
{
    #[DataProvider('provideBaseUrls')]
    public function testGetModelsRequestsVersionedModelsEndpoint(string $baseUrl)
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://albert.example.com/v1/models', $url);

            return new JsonMockResponse([
                'data' => [
                    ['id' => 'albert-large'],
                    ['id' => 'albert-small'],
                ],
            ]);
        });

        $apiClient = new ApiClient($baseUrl, 'test-key', $httpClient);
        $models = $apiClient->getModels();

        $this->assertCount(2, $models);
        $this->assertContainsOnlyInstancesOf(Model::class, $models);
        $this->assertSame('albert-large', $models[0]->getName());
        $this->assertSame('albert-small', $models[1]->getName());
    }

    public static function provideBaseUrls(): \Iterator
    {
        yield 'base url without version' => ['https://albert.example.com'];
        yield 'base url with trailing slash' => ['https://albert.example.com/'];
    }
}
