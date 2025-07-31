<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\ClickHouse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\ClickHouse\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Store::class)]
final class StoreTest extends TestCase
{
    public function testInitialize()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $expectedSql = 'CREATE TABLE IF NOT EXISTS test_table (
                id UUID,
                metadata String,
                embedding Array(Float32),
            ) ENGINE = MergeTree()
            ORDER BY id';

        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', '/', $this->callback(function ($options) use ($expectedSql) {
                return str_replace([' ', "\n", "\t"], '', $options['query']['query']) ===
                       str_replace([' ', "\n", "\t"], '', $expectedSql);
            }))
            ->willReturn($response);

        $store->initialize();
    }

    public function testAddSingleDocument()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $uuid = Uuid::v4();
        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document']));

        $expectedJsonData = json_encode([
            'id' => $uuid->toRfc4122(),
            'metadata' => json_encode(['title' => 'Test Document']),
            'embedding' => [0.1, 0.2, 0.3],
        ]) . "\n";

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', '/', $this->callback(function ($options) use ($expectedJsonData) {
                return $options['body'] === $expectedJsonData &&
                       $options['query']['query'] === 'INSERT INTO test_table FORMAT JSONEachRow' &&
                       $options['headers']['Content-Type'] === 'application/json';
            }))
            ->willReturn($response);

        $store->add($document);
    }

    public function testAddMultipleDocuments()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();
        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]));
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second']));

        $expectedJsonData = json_encode([
            'id' => $uuid1->toRfc4122(),
            'metadata' => json_encode([]),
            'embedding' => [0.1, 0.2, 0.3],
        ]) . "\n" . json_encode([
            'id' => $uuid2->toRfc4122(),
            'metadata' => json_encode(['title' => 'Second']),
            'embedding' => [0.4, 0.5, 0.6],
        ]) . "\n";

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', '/', $this->callback(function ($options) use ($expectedJsonData) {
                return $options['body'] === $expectedJsonData;
            }))
            ->willReturn($response);

        $store->add($document1, $document2);
    }

    public function testAddThrowsExceptionOnHttpError()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $uuid = Uuid::v4();
        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]));

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);

        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('Internal Server Error');

        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not insert data into ClickHouse. Http status code: 500. Response: Internal Server Error.');

        $store->add($document);
    }

    public function testQuery()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $queryVector = new Vector([0.1, 0.2, 0.3]);
        $uuid = Uuid::v4();

        $responseData = [
            'data' => [
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => [0.1, 0.2, 0.3],
                    'metadata' => json_encode(['title' => 'Test Document']),
                    'score' => 0.95,
                ],
            ],
        ];

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($responseData);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', '/', $this->callback(function ($options) {
                return isset($options['query']['param_query_vector']) &&
                       $options['query']['param_query_vector'] === '[0.1,0.2,0.3]' &&
                       isset($options['query']['param_limit']) &&
                       $options['query']['param_limit'] === 5;
            }))
            ->willReturn($response);

        $results = $store->query($queryVector);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertEquals($uuid, $results[0]->id);
        $this->assertSame(0.95, $results[0]->score);
        $this->assertSame(['title' => 'Test Document'], $results[0]->metadata->getArrayCopy());
    }

    public function testQueryWithOptions()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $queryVector = new Vector([0.1, 0.2, 0.3]);

        $responseData = ['data' => []];

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($responseData);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', '/', $this->callback(function ($options) {
                return $options['query']['param_limit'] === 10 &&
                       isset($options['query']['param_custom_param']) &&
                       $options['query']['param_custom_param'] === 'test_value';
            }))
            ->willReturn($response);

        $results = $store->query($queryVector, [
            'limit' => 10,
            'params' => ['custom_param' => 'test_value'],
        ]);

        $this->assertCount(0, $results);
    }

    public function testQueryWithWhereClause()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $queryVector = new Vector([0.1, 0.2, 0.3]);

        $responseData = ['data' => []];

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($responseData);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', '/', $this->callback(function ($options) {
                return str_contains($options['query']['query'], "AND JSONExtractString(metadata, 'type') = 'document'");
            }))
            ->willReturn($response);

        $results = $store->query($queryVector, [
            'where' => "JSONExtractString(metadata, 'type') = 'document'",
        ]);

        $this->assertCount(0, $results);
    }

    public function testQueryWithNullMetadata()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $store = new Store($httpClient, 'test_db', 'test_table');

        $queryVector = new Vector([0.1, 0.2, 0.3]);
        $uuid = Uuid::v4();

        $responseData = [
            'data' => [
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => [0.1, 0.2, 0.3],
                    'metadata' => null,
                    'score' => 0.95,
                ],
            ],
        ];

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($responseData);

        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $results = $store->query($queryVector);

        $this->assertCount(1, $results);
        $this->assertSame([], $results[0]->metadata->getArrayCopy());
    }
}
