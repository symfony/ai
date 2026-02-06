<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\S3Vectors\Tests;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3Vectors\Enum\DataType;
use AsyncAws\S3Vectors\Enum\DistanceMetric;
use AsyncAws\S3Vectors\Result\QueryVectorsOutput;
use AsyncAws\S3Vectors\S3VectorsClient;
use AsyncAws\S3Vectors\ValueObject\QueryOutputVector;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\S3Vectors\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testAddSingleDocument()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $uuid = Uuid::v4();

        $client->expects($this->once())
            ->method('putVectors')
            ->with($this->callback(static function ($input) use ($uuid) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && 1 === \count($input['vectors'])
                    && (string) $uuid === $input['vectors'][0]->getKey()
                    && [0.1, 0.2, 0.3] === $input['vectors'][0]->getData()->requestBody()['float32'];
            }));

        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document']));
        self::createStore($client)->add($document);
    }

    public function testAddMultipleDocuments()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $client->expects($this->once())
            ->method('putVectors')
            ->with($this->callback(static function ($input) use ($uuid1, $uuid2) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && 2 === \count($input['vectors'])
                    && (string) $uuid1 === $input['vectors'][0]->getKey()
                    && (string) $uuid2 === $input['vectors'][1]->getKey();
            }));

        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]));
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second Document']));

        self::createStore($client)->add([$document1, $document2]);
    }

    public function testAddWithEmptyDocuments()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $client->expects($this->never())
            ->method('putVectors');

        self::createStore($client)->add([]);
    }

    public function testRemoveSingleDocument()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $vectorId = 'vector-id';

        $client->expects($this->once())
            ->method('deleteVectors')
            ->with($this->callback(static function ($input) use ($vectorId) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && $input['keys'] === [$vectorId];
            }));

        self::createStore($client)->remove($vectorId);
    }

    public function testRemoveMultipleDocuments()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $documents = ['vector-id-1', 'vector-id-2', 'vector-id-3'];

        $client->expects($this->once())
            ->method('deleteVectors')
            ->with($this->callback(static function ($input) use ($documents) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && $input['keys'] === $documents;
            }));

        self::createStore($client)->remove($documents);
    }

    public function testRemoveWithEmptyDocuments()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $client->expects($this->never())
            ->method('deleteVectors');

        self::createStore($client)->remove([]);
    }

    public function testRemoveWithFilter()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $filter = ['category' => 'test'];

        $client->expects($this->once())
            ->method('deleteVectors')
            ->with($this->callback(static function ($input) use ($filter) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && $input['filter'] === $filter;
            }));

        self::createStore($client)->remove(['id-1'], ['filter' => $filter]);
    }

    public function testQueryReturnsDocuments()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $result = ResultMockFactory::create(QueryVectorsOutput::class, [
            'vectors' => [
                new QueryOutputVector([
                    'key' => (string) $uuid1,
                    'metadata' => ['title' => 'First Document'],
                    'distance' => 0.95,
                ]),
                new QueryOutputVector([
                    'key' => (string) $uuid2,
                    'metadata' => ['title' => 'Second Document'],
                    'distance' => 0.85,
                ]),
            ],
            'distanceMetric' => DistanceMetric::COSINE,
        ]);

        $client->expects($this->once())
            ->method('queryVectors')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && [0.1, 0.2, 0.3] === $input['queryVector']->requestBody()['float32']
                    && 3 === $input['topK']
                    && true === $input['returnMetadata']
                    && true === $input['returnDistance'];
            }))
            ->willReturn($result);

        $results = iterator_to_array(self::createStore($client)->query(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(2, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertInstanceOf(VectorDocument::class, $results[1]);
        $this->assertEquals($uuid1, $results[0]->id);
        $this->assertEquals($uuid2, $results[1]->id);
        $this->assertSame(0.95, $results[0]->score);
        $this->assertSame(0.85, $results[1]->score);
        $this->assertSame('First Document', $results[0]->metadata['title']);
        $this->assertSame('Second Document', $results[1]->metadata['title']);
    }

    public function testQueryWithCustomOptions()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $result = ResultMockFactory::create(QueryVectorsOutput::class, [
            'vectors' => [],
            'distanceMetric' => DistanceMetric::COSINE,
        ]);

        $client->expects($this->once())
            ->method('queryVectors')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && 10 === $input['topK']
                    && ['type' => 'document'] === $input['filter'];
            }))
            ->willReturn($result);

        $results = iterator_to_array(self::createStore($client)->query(new Vector([0.1, 0.2, 0.3]), [
            'topK' => 10,
            'filter' => ['type' => 'document'],
        ]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithEmptyResults()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $result = ResultMockFactory::create(QueryVectorsOutput::class, [
            'vectors' => [],
            'distanceMetric' => DistanceMetric::COSINE,
        ]);

        $client->expects($this->once())
            ->method('queryVectors')
            ->willReturn($result);

        $results = iterator_to_array(self::createStore($client)->query(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(0, $results);
    }

    public function testSetup()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $client->expects($this->once())
            ->method('getVectorBucket')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName'];
            }))
            ->willThrowException(new \Exception('Bucket not found'));

        $client->expects($this->once())
            ->method('createVectorBucket')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName'];
            }));

        $client->expects($this->once())
            ->method('createIndex')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && 1536 === $input['dimension']
                    && DistanceMetric::COSINE === $input['distanceMetric']
                    && DataType::FLOAT_32 === $input['dataType'];
            }));

        self::createStore($client)->setup([
            'dimension' => 1536,
        ]);
    }

    public function testSetupWithCustomOptions()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $client->expects($this->once())
            ->method('getVectorBucket')
            ->willThrowException(new \Exception('Bucket not found'));

        $client->expects($this->once())
            ->method('createVectorBucket')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName']
                    && isset($input['encryptionConfiguration'])
                    && 'test-key' === $input['encryptionConfiguration']['kmsKeyId']
                    && ['env' => 'test'] === $input['tags'];
            }));

        $client->expects($this->once())
            ->method('createIndex')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName']
                    && 384 === $input['dimension']
                    && DistanceMetric::EUCLIDEAN === $input['distanceMetric']
                    && isset($input['encryptionConfiguration'])
                    && 'test-key' === $input['encryptionConfiguration']['kmsKeyId']
                    && ['env' => 'test'] === $input['tags'];
            }));

        self::createStore($client)->setup([
            'dimension' => 384,
            'distanceMetric' => DistanceMetric::EUCLIDEAN,
            'encryption' => ['kmsKeyId' => 'test-key'],
            'tags' => ['env' => 'test'],
        ]);
    }

    public function testSetupThrowsExceptionWithoutDimension()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "dimension" option is required.');

        self::createStore($client)->setup([]);
    }

    public function testDrop()
    {
        $client = $this->createMock(S3VectorsClient::class);

        $client->expects($this->once())
            ->method('deleteIndex')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName']
                    && 'test-index' === $input['indexName'];
            }));

        $client->expects($this->once())
            ->method('deleteVectorBucket')
            ->with($this->callback(static function ($input) {
                return 'test-bucket' === $input['vectorBucketName'];
            }));

        self::createStore($client)->drop();
    }

    /**
     * @param array<string, mixed> $filter
     */
    private static function createStore(S3VectorsClient $client, string $vectorBucketName = 'test-bucket', string $indexName = 'test-index', array $filter = [], int $topK = 3): Store
    {
        return new Store($client, $vectorBucketName, $indexName, $filter, $topK);
    }
}
