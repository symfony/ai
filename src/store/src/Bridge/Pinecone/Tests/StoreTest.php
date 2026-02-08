<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone\Tests;

use PHPUnit\Framework\TestCase;
use Probots\Pinecone\Client;
use Probots\Pinecone\Resources\Control\IndexResource;
use Probots\Pinecone\Resources\ControlResource;
use Probots\Pinecone\Resources\Data\VectorResource;
use Probots\Pinecone\Resources\DataResource;
use Saloon\Http\Response;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Pinecone\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testAddSingleDocument()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $uuid = Uuid::v4();

        $vectorResource->expects($this->once())
            ->method('upsert')
            ->with(
                [
                    [
                        'id' => (string) $uuid,
                        'values' => [0.1, 0.2, 0.3],
                        'metadata' => ['title' => 'Test Document'],
                    ],
                ],
                null,
            );

        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document']));
        self::createStore($client)->add($document);
    }

    public function testAddMultipleDocuments()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $vectorResource->expects($this->once())
            ->method('upsert')
            ->with(
                [
                    [
                        'id' => (string) $uuid1,
                        'values' => [0.1, 0.2, 0.3],
                        'metadata' => [],
                    ],
                    [
                        'id' => (string) $uuid2,
                        'values' => [0.4, 0.5, 0.6],
                        'metadata' => ['title' => 'Second Document'],
                    ],
                ],
                null,
            );

        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]));
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second Document']));

        self::createStore($client)->add([$document1, $document2]);
    }

    public function testAddWithNamespace()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $uuid = Uuid::v4();

        $vectorResource->expects($this->once())
            ->method('upsert')
            ->with(
                [
                    [
                        'id' => (string) $uuid,
                        'values' => [0.1, 0.2, 0.3],
                        'metadata' => [],
                    ],
                ],
                'test-namespace',
            );

        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]));
        self::createStore($client, namespace: 'test-namespace')->add($document);
    }

    public function testAddWithEmptyDocuments()
    {
        $client = $this->createMock(Client::class);

        $client->expects($this->never())
            ->method('data');

        self::createStore($client)->add([]);
    }

    public function testRemoveSingleDocument()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $vectorId = 'vector-id';

        $vectorResource->expects($this->once())
            ->method('delete')
            ->with([$vectorId],
                'test-namespace',
                false,
                [],
            );

        self::createStore($client, namespace: 'test-namespace')->remove($vectorId);
    }

    public function testRemoveMultipleDocuments()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $vectorResource->expects($this->exactly(2))
            ->method('delete');

        $documents = [];
        for ($i = 0; $i < 1001; ++$i) {
            $documents[] = 'vector-id-'.$i;
        }

        self::createStore($client)->remove($documents);
    }

    public function testRemoveWithEmptyDocuments()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->method('vectors')
            ->willReturn($vectorResource);

        $client->method('data')
            ->willReturn($dataResource);

        $vectorResource->expects($this->never())
            ->method('delete');

        self::createStore($client)->remove([]);
    }

    public function testQueryReturnsDocuments()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $response = $this->createMock(Response::class);
        $response->method('json')->willReturn([
            'matches' => [
                [
                    'id' => (string) $uuid1,
                    'values' => [0.1, 0.2, 0.3],
                    'metadata' => ['title' => 'First Document'],
                    'score' => 0.95,
                ],
                [
                    'id' => (string) $uuid2,
                    'values' => [0.4, 0.5, 0.6],
                    'metadata' => ['title' => 'Second Document'],
                    'score' => 0.85,
                ],
            ],
        ]);

        $vectorResource->expects($this->once())
            ->method('query')
            ->with(
                [0.1, 0.2, 0.3], // vector
                null, // namespace
                [], // filter
                3, // topK
                true, // includeValues
            )
            ->willReturn($response);

        $results = iterator_to_array(self::createStore($client)->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertInstanceOf(VectorDocument::class, $results[1]);
        $this->assertEquals($uuid1, $results[0]->getId());
        $this->assertEquals($uuid2, $results[1]->getId());
        $this->assertSame(0.95, $results[0]->getScore());
        $this->assertSame(0.85, $results[1]->getScore());
        $this->assertSame('First Document', $results[0]->getMetadata()['title']);
        $this->assertSame('Second Document', $results[1]->getMetadata()['title']);
    }

    public function testQueryWithNamespaceAndFilter()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $response = $this->createMock(Response::class);
        $response->method('json')->willReturn([
            'matches' => [],
        ]);

        $vectorResource->expects($this->once())
            ->method('query')
            ->with(
                [0.1, 0.2, 0.3], // vector
                'test-namespace', // namespace
                ['category' => 'test'], // filter
                5, // topK
                true, // includeValues
            )
            ->willReturn($response);

        $results = iterator_to_array(self::createStore($client, namespace: 'test-namespace', filter: ['category' => 'test'], topK: 5)->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomOptions()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $response = $this->createMock(Response::class);
        $response->method('json')->willReturn([
            'matches' => [],
        ]);

        $vectorResource->expects($this->once())
            ->method('query')
            ->with(
                [0.1, 0.2, 0.3], // vector
                'custom-namespace', // namespace
                ['type' => 'document'], // filter
                10, // topK
                true, // includeValues
            )
            ->willReturn($response);

        $results = iterator_to_array(self::createStore($client)->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), [
            'namespace' => 'custom-namespace',
            'filter' => ['type' => 'document'],
            'topK' => 10,
        ]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithEmptyResults()
    {
        $vectorResource = $this->createMock(VectorResource::class);
        $dataResource = $this->createMock(DataResource::class);
        $client = $this->createMock(Client::class);

        $dataResource->expects($this->once())
            ->method('vectors')
            ->willReturn($vectorResource);

        $client->expects($this->once())
            ->method('data')
            ->willReturn($dataResource);

        $response = $this->createMock(Response::class);
        $response->method('json')->willReturn([
            'matches' => [],
        ]);

        $vectorResource->expects($this->once())
            ->method('query')
            ->willReturn($response);

        $results = iterator_to_array(self::createStore($client)->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results);
    }

    public function testSetup()
    {
        $indexResource = $this->createMock(IndexResource::class);
        $controlResource = $this->createMock(ControlResource::class);
        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('control')
            ->willReturn($controlResource);

        $controlResource->expects($this->once())
            ->method('index')
            ->with('my-index')
            ->willReturn($indexResource);

        $indexResource->expects($this->once())
            ->method('createServerless')
            ->with(1536, 'cosine', 'aws', 'us-east-1');

        self::createStore($client, indexName: 'my-index')->setup([
            'dimension' => 1536,
            'metric' => 'cosine',
            'cloud' => 'aws',
            'region' => 'us-east-1',
        ]);
    }

    public function testSetupThrowsExceptionWithoutDimension()
    {
        $client = $this->createMock(Client::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "dimension" option is required.');

        self::createStore($client, indexName: 'my-index')->setup([]);
    }

    public function testDrop()
    {
        $indexResource = $this->createMock(IndexResource::class);
        $controlResource = $this->createMock(ControlResource::class);
        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('control')
            ->willReturn($controlResource);

        $controlResource->expects($this->once())
            ->method('index')
            ->with('my-index')
            ->willReturn($indexResource);

        $indexResource->expects($this->once())
            ->method('delete');

        self::createStore($client, indexName: 'my-index')->drop();
    }

    public function testStoreSupportsVectorQuery()
    {
        $client = $this->createMock(Client::class);
        $store = new Store($client, 'test-index');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotTextQuery()
    {
        $client = $this->createMock(Client::class);
        $store = new Store($client, 'test-index');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $client = $this->createMock(Client::class);
        $store = new Store($client, 'test-index');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    /**
     * @param array<string, mixed> $filter
     */
    private static function createStore(Client $client, string $indexName = 'test-index', ?string $namespace = null, array $filter = [], int $topK = 3): Store
    {
        return new Store($client, $indexName, $namespace, $filter, $topK);
    }
}
