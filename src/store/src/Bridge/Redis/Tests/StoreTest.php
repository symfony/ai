<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Redis\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Redis\Distance;
use Symfony\AI\Store\Bridge\Redis\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testAddSingleDocument()
    {
        $redis = $this->createMock(\Redis::class);
        $pipeline = $this->createMock(\Redis::class);

        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('multi')
            ->with(\Redis::PIPELINE)
            ->willReturn($pipeline);

        $uuid = Uuid::v4()->toString();
        $expectedKey = 'vector:'.$uuid;
        $expectedData = [
            'id' => $uuid,
            'metadata' => ['title' => 'Test Document'],
            'embedding' => [0.1, 0.2, 0.3],
        ];

        $pipeline->expects($this->once())
            ->method('rawCommand')
            ->with('JSON.SET', $expectedKey, '$', json_encode($expectedData, \JSON_THROW_ON_ERROR));

        $pipeline->expects($this->once())
            ->method('exec');

        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document']));
        $store->add($document);
    }

    public function testAddMultipleDocuments()
    {
        $redis = $this->createMock(\Redis::class);
        $pipeline = $this->createMock(\Redis::class);

        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('multi')
            ->with(\Redis::PIPELINE)
            ->willReturn($pipeline);

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $pipeline->expects($this->exactly(2))
            ->method('rawCommand')
            ->willReturnCallback(function (string $command, string $key, string $path, string $data): void {
                static $callCount = 0;
                ++$callCount;

                $this->assertSame('JSON.SET', $command);
                $this->assertSame('$', $path);

                $decodedData = json_decode($data, true);
                if (1 === $callCount) {
                    $this->assertSame([], $decodedData['metadata']);
                    $this->assertSame([0.1, 0.2, 0.3], $decodedData['embedding']);
                } else {
                    $this->assertSame(['title' => 'Second'], $decodedData['metadata']);
                    $this->assertSame([0.4, 0.5, 0.6], $decodedData['embedding']);
                }
            });

        $pipeline->expects($this->once())
            ->method('exec');

        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]));
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second']));

        $store->add([$document1, $document2]);
    }

    public function testQueryWithoutMaxScore()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $uuid = Uuid::v4()->toString();

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.SEARCH',
                'test_index',
                '(*) => [KNN 5 @embedding $query_vector AS vector_score]',
                'PARAMS', 2, 'query_vector', $this->isType('string'),
                'RETURN', 4, '$.id', '$.metadata', '$.embedding', 'vector_score',
                'SORTBY', 'vector_score', 'ASC',
                'LIMIT', 0, 5,
                'DIALECT', 2
            )
            ->willReturn([
                1, // number of results
                'vector:'.$uuid, // document key
                [
                    '$.id', $uuid,
                    '$.metadata', json_encode(['title' => 'Test Document']),
                    '$.embedding', json_encode([0.1, 0.2, 0.3]),
                    'vector_score', '0.95',
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertEquals($uuid, $results[0]->getId());
        $this->assertSame(0.95, $results[0]->getScore());
        $this->assertSame(['title' => 'Test Document'], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryChangedDistanceMethodWithoutMaxScore()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:', Distance::L2);

        $uuid = Uuid::v4()->toString();

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.SEARCH',
                'test_index',
                '(*) => [KNN 5 @embedding $query_vector AS vector_score]',
                'PARAMS', 2, 'query_vector', $this->isType('string'),
                'RETURN', 4, '$.id', '$.metadata', '$.embedding', 'vector_score',
                'SORTBY', 'vector_score', 'ASC',
                'LIMIT', 0, 5,
                'DIALECT', 2
            )
            ->willReturn([
                1,
                'vector:'.$uuid,
                [
                    '$.id', $uuid,
                    '$.metadata', json_encode(['title' => 'Test Document']),
                    '$.embedding', json_encode([0.1, 0.2, 0.3]),
                    'vector_score', '0.95',
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertEquals($uuid, $results[0]->getId());
        $this->assertSame(0.95, $results[0]->getScore());
        $this->assertSame(['title' => 'Test Document'], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryWithMaxScore()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willReturn([
                1,
                'vector:some-uuid',
                [
                    '$.id', 'some-uuid',
                    '$.metadata', json_encode(['title' => 'Test Document']),
                    '$.embedding', json_encode([0.1, 0.2, 0.3]),
                    'vector_score', '0.95', // Score higher than maxScore, should be filtered
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxScore' => 0.8]));

        $this->assertCount(0, $results); // Should be filtered out due to maxScore
    }

    public function testQueryWithCustomLimit()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.SEARCH',
                'test_index',
                '(*) => [KNN 10 @embedding $query_vector AS vector_score]',
                'PARAMS', 2, 'query_vector', $this->isType('string'),
                'RETURN', 4, '$.id', '$.metadata', '$.embedding', 'vector_score',
                'SORTBY', 'vector_score', 'ASC',
                'LIMIT', 0, 10,
                'DIALECT', 2
            )
            ->willReturn([0]); // No results

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.7, 0.8, 0.9])), ['limit' => 10]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomWhereExpression()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.SEARCH',
                'test_index',
                '(@metadata_category:products) => [KNN 5 @embedding $query_vector AS vector_score]',
                'PARAMS', 2, 'query_vector', $this->isType('string'),
                'RETURN', 4, '$.id', '$.metadata', '$.embedding', 'vector_score',
                'SORTBY', 'vector_score', 'ASC',
                'LIMIT', 0, 5,
                'DIALECT', 2
            )
            ->willReturn([0]); // No results

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['where' => '@metadata_category:products']));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomWhereExpressionAndMaxScore()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.SEARCH',
                'test_index',
                '(@metadata_active:true) => [KNN 5 @embedding $query_vector AS vector_score]',
                'PARAMS', 2, 'query_vector', $this->isType('string'),
                'RETURN', 4, '$.id', '$.metadata', '$.embedding', 'vector_score',
                'SORTBY', 'vector_score', 'ASC',
                'LIMIT', 0, 5,
                'DIALECT', 2
            )
            ->willReturn([
                1,
                'vector:some-uuid',
                [
                    '$.id', 'some-uuid',
                    '$.metadata', json_encode(['active' => true]),
                    '$.embedding', json_encode([0.1, 0.2, 0.3]),
                    'vector_score', '0.95', // Higher than maxScore
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), [
            'where' => '@metadata_active:true',
            'maxScore' => 0.8,
        ]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithNullMetadata()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $uuid = Uuid::v4();

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willReturn([
                1,
                'vector:'.$uuid->toRfc4122(),
                [
                    '$.id', $uuid->toRfc4122(),
                    '$.metadata', null, // Null metadata
                    '$.embedding', json_encode([0.1, 0.2, 0.3]),
                    'vector_score', '0.85',
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);
        $this->assertSame([], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryFailureThrowsRuntimeException()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willThrowException(new \RedisException('Search failed'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute query: "Search failed".');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testInitialize()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.CREATE', 'test_index', 'ON', 'JSON',
                'PREFIX', '1', 'vector:',
                'SCHEMA',
                '$.id', 'AS', 'id', 'TEXT',
                '$.embedding', 'AS', 'embedding', 'VECTOR', 'FLAT', '6', 'TYPE', 'FLOAT32', 'DIM', 3072, 'DISTANCE_METRIC', 'COSINE'
            );

        $store->setup();
    }

    public function testInitializeWithCustomVectorSize()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.CREATE', 'test_index', 'ON', 'JSON',
                'PREFIX', '1', 'vector:',
                'SCHEMA',
                '$.id', 'AS', 'id', 'TEXT',
                '$.embedding', 'AS', 'embedding', 'VECTOR', 'FLAT', '6', 'TYPE', 'FLOAT32', 'DIM', 768, 'DISTANCE_METRIC', 'COSINE'
            );

        $store->setup(['vector_size' => 768]);
    }

    public function testInitializeWithCustomIndexMethod()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:', Distance::L2);

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.CREATE', 'test_index', 'ON', 'JSON',
                'PREFIX', '1', 'vector:',
                'SCHEMA',
                '$.id', 'AS', 'id', 'TEXT',
                '$.embedding', 'AS', 'embedding', 'VECTOR', 'HNSW', '6', 'TYPE', 'FLOAT32', 'DIM', 1024, 'DISTANCE_METRIC', 'L2'
            );

        $store->setup([
            'vector_size' => 1024,
            'index_method' => 'HNSW',
        ]);
    }

    public function testInitializeWithExtraSchema()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                'FT.CREATE', 'test_index', 'ON', 'JSON',
                'PREFIX', '1', 'vector:',
                'SCHEMA',
                '$.id', 'AS', 'id', 'TEXT',
                '$.embedding', 'AS', 'embedding', 'VECTOR', 'FLAT', '6', 'TYPE', 'FLOAT32', 'DIM', 3072, 'DISTANCE_METRIC', 'COSINE',
                '$.metadata.title', 'AS', 'title', 'TEXT'
            );

        $store->setup([
            'extra_schema' => ['$.metadata.title', 'AS', 'title', 'TEXT'],
        ]);
    }

    public function testInitializeIndexAlreadyExists()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willThrowException(new \RedisException('Index already exists'));

        // Should not throw an exception when index already exists
        $store->setup();
    }

    public function testInitializeFailureThrowsRuntimeException()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willThrowException(new \RedisException('Connection failed'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create Redis index: "Connection failed".');

        $store->setup();
    }

    public function testToRedisVectorConversion()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        // Test the private toRedisVector method indirectly through query
        $redis->expects($this->once())
            ->method('rawCommand')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'query_vector',
                $this->callback(static function ($vectorBytes) {
                    // Vector [0.1, 0.2, 0.3] packed as 32-bit floats
                    $expected = pack('f', 0.1).pack('f', 0.2).pack('f', 0.3);

                    return $vectorBytes === $expected;
                }),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn([0]);

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testQueryEmptyResults()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willReturn([0]); // No results

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results);
    }

    public function testQueryInvalidResults()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willReturn(null); // Invalid results

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results);
    }

    public function testQueryMissingRequiredFields()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test_index', 'vector:');

        $redis->expects($this->once())
            ->method('rawCommand')
            ->willReturn([
                1,
                'vector:some-uuid',
                [
                    '$.metadata', json_encode(['title' => 'Test Document']),
                    '$.embedding', json_encode([0.1, 0.2, 0.3]),
                    // Missing $.id and vector_score
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results); // Should skip documents without required fields
    }

    public function testStoreSupportsVectorQuery()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test:vectors');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test:vectors');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new Store($redis, 'test:vectors');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
