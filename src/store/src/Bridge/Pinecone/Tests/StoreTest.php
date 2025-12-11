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
use Probots\Pinecone\Resources\Data\VectorResource;
use Probots\Pinecone\Resources\DataResource;
use Saloon\Http\Response;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Pinecone\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
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

        $store = new Store($client);

        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document']));
        $store->add($document);
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

        $store = new Store($client);

        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]));
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second Document']));

        $store->add($document1, $document2);
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

        $store = new Store($client, 'test-namespace');

        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]));
        $store->add($document);
    }

    public function testAddWithEmptyDocuments()
    {
        $client = $this->createMock(Client::class);

        $client->expects($this->never())
            ->method('data');

        $store = new Store($client);
        $store->add();
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

        $store = new Store($client);

        $results = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));

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

        $store = new Store($client, 'test-namespace', ['category' => 'test'], 5);

        $results = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));

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

        $store = new Store($client);

        $results = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3]), [
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

        $store = new Store($client);

        $results = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(0, $results);
    }
}
