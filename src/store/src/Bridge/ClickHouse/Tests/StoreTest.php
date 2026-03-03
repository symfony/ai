<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ClickHouse\Tests;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\ClickHouse\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testInitialize()
    {
        $expectedRequests = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$expectedRequests) {
            $expectedRequests[] = compact('method', 'url', 'options');

            $expectedSql = 'CREATE TABLE IF NOT EXISTS test_table (
                id UUID,
                metadata String,
                embedding Array(Float32),
            ) ENGINE = MergeTree()
            ORDER BY id';

            $this->assertSame('POST', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $this->assertEquals(
                str_replace([' ', "\n", "\t"], '', $expectedSql),
                str_replace([' ', "\n", "\t"], '', $options['query']['query'])
            );

            return new MockResponse('');
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $store->setup();

        $this->assertCount(1, $expectedRequests);
    }

    public function testAddSingleDocument()
    {
        $uuid = Uuid::v4()->toString();
        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document']));

        $expectedJsonData = json_encode([
            'id' => $uuid,
            'metadata' => json_encode(['title' => 'Test Document']),
            'embedding' => [0.1, 0.2, 0.3],
        ])."\n";

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($expectedJsonData) {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $this->assertSame($expectedJsonData, $options['body']);
            $this->assertSame('INSERT INTO test_table FORMAT JSONEachRow', $options['query']['query']);
            $this->assertSame('Content-Type: application/json', $options['headers'][0]);

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $store->add($document);
    }

    public function testAddMultipleDocuments()
    {
        $uuid1 = Uuid::v4()->toString();
        $uuid2 = Uuid::v4()->toString();
        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]));
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second']));

        $expectedJsonData = json_encode([
            'id' => $uuid1,
            'metadata' => json_encode([]),
            'embedding' => [0.1, 0.2, 0.3],
        ])."\n".json_encode([
            'id' => $uuid2,
            'metadata' => json_encode(['title' => 'Second']),
            'embedding' => [0.4, 0.5, 0.6],
        ])."\n";

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($expectedJsonData) {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $this->assertSame($expectedJsonData, $options['body']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $store->add([$document1, $document2]);
    }

    public function testAddThrowsExceptionOnHttpError()
    {
        $uuid = Uuid::v4()->toString();
        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]));

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) {
            return new MockResponse('Internal Server Error', ['http_code' => 500]);
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not insert data into ClickHouse. Http status code: 500. Response: "Internal Server Error".');

        $store->add($document);
    }

    public function testQuery()
    {
        $queryVector = new Vector([0.1, 0.2, 0.3]);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $this->assertSame('[0.1,0.2,0.3]', $options['query']['param_query_vector']);
            $this->assertSame(5, $options['query']['param_limit']);

            return new MockResponse(json_encode([
                'data' => [
                    [
                        'id' => '01234567-89ab-cdef-0123-456789abcdef',
                        'embedding' => [0.1, 0.2, 0.3],
                        'metadata' => json_encode(['title' => 'Test Document']),
                        'score' => 0.95,
                    ],
                ],
            ]));
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $results = iterator_to_array($store->query(new VectorQuery($queryVector)));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame(0.95, $results[0]->getScore());
        $this->assertSame(['title' => 'Test Document'], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryWithOptions()
    {
        $queryVector = new Vector([0.1, 0.2, 0.3]);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $this->assertSame(10, $options['query']['param_limit']);
            $this->assertSame('test_value', $options['query']['param_custom_param']);

            return new MockResponse(json_encode(['data' => []]));
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $results = iterator_to_array($store->query(new VectorQuery($queryVector), [
            'limit' => 10,
            'params' => ['custom_param' => 'test_value'],
        ]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithWhereClause()
    {
        $queryVector = new Vector([0.1, 0.2, 0.3]);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $this->assertStringContainsString("AND JSONExtractString(metadata, 'type') = 'document'", $options['query']['query']);

            return new MockResponse(json_encode(['data' => []]));
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $results = iterator_to_array($store->query(new VectorQuery($queryVector), [
            'where' => "JSONExtractString(metadata, 'type') = 'document'",
        ]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithNullMetadata()
    {
        $queryVector = new Vector([0.1, 0.2, 0.3]);

        $responseData = [
            'data' => [
                [
                    'id' => Uuid::v4()->toString(),
                    'embedding' => [0.1, 0.2, 0.3],
                    'metadata' => null,
                    'score' => 0.95,
                ],
            ],
        ];

        $httpClient = new MockHttpClient(static function () use ($responseData) {
            return new MockResponse(json_encode($responseData));
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $results = iterator_to_array($store->query(new VectorQuery($queryVector)));

        $this->assertCount(1, $results);
        $this->assertSame([], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testRemoveSingleDocument()
    {
        $id = 'a0d23a85-abfb-4696-a652-73a83b46710b';

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $expectedSql = 'DELETE FROM test_table WHERE id IN {ids:Array(UUID)}';
            $this->assertSame($expectedSql, $options['query']['query']);
            $expectedId = "['a0d23a85-abfb-4696-a652-73a83b46710b']";
            $this->assertSame($expectedId, $options['query']['param_ids']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $store->remove($id);
    }

    public function testRemoveMultipleDocuments()
    {
        $id1 = '93c1d46a-2a30-4466-a28b-8e92e935a29b';
        $id2 = 'f5667ae9-e0f1-4fa0-ab16-37e0136680ca';
        $expectedIds = "['93c1d46a-2a30-4466-a28b-8e92e935a29b','f5667ae9-e0f1-4fa0-ab16-37e0136680ca']";

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($expectedIds) {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('?', $url); // Check that URL has query parameters
            $expectedSql = 'DELETE FROM test_table WHERE id IN {ids:Array(UUID)}';
            $this->assertSame($expectedSql, $options['query']['query']);
            $this->assertSame($expectedIds, $options['query']['param_ids']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $store->remove([$id1, $id2]);
    }

    #[DoesNotPerformAssertions]
    public function testRemoveWithEmptyArray()
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('Should not make any request when removing empty array');
        });

        $store = new Store($httpClient, 'test_db', 'test_table');

        $store->remove([]);
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'default', 'test_table');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreNotSupportsTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'default', 'test_table');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreNotSupportsHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'default', 'test_table');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
