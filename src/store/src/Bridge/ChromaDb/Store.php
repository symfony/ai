<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ChromaDb;

use Codewithkyrian\ChromaDB\Client;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store implements StoreInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $collectionName,
    ) {
        if (!class_exists(Client::class)) {
            throw new RuntimeException('For using the ChromaDB as retrieval vector store, the codewithkyrian/chromadb-php package is required. Try running "composer require codewithkyrian/chromadb-php".');
        }
    }

    public function add(VectorDocument ...$documents): void
    {
        $ids = [];
        $vectors = [];
        $metadata = [];
        $originalDocuments = [];
        foreach ($documents as $document) {
            $ids[] = (string) $document->id;
            $vectors[] = $document->vector->getData();
            $metadataCopy = $document->metadata->getArrayCopy();
            $originalDocuments[] = $document->metadata->getText() ?? '';
            unset($metadataCopy[Metadata::KEY_TEXT]);
            $metadata[] = $metadataCopy;
        }

        $collection = $this->client->getOrCreateCollection($this->collectionName);
        $collection->add($ids, $vectors, $metadata, $originalDocuments);
    }

    /**
     * @param array{where?: array<string, string>, whereDocument?: array<string, mixed>} $options
     */
    public function query(Vector $vector, array $options = []): iterable
    {
        $collection = $this->client->getOrCreateCollection($this->collectionName);
        $queryResponse = $collection->query(
            queryEmbeddings: [$vector->getData()],
            nResults: 4,
            where: $options['where'] ?? null,
            whereDocument: $options['whereDocument'] ?? null,
        );

        for ($i = 0; $i < \count($queryResponse->metadatas[0]); ++$i) {
            yield new VectorDocument(
                id: Uuid::fromString($queryResponse->ids[0][$i]),
                vector: new Vector($queryResponse->embeddings[0][$i]),
                metadata: new Metadata($queryResponse->metadatas[0][$i]),
                score: $queryResponse->distances[0][$i] ?? null,
            );
        }
    }
}
