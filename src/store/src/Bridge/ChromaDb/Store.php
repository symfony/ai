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
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

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

        // @phpstan-ignore argument.type (chromadb-php library has incorrect PHPDoc type for $metadatas parameter)
        $collection->add($ids, $vectors, $metadata, $originalDocuments);
    }

    /**
     * @param array{where?: array<string, string>, whereDocument?: array<string, mixed>, include?: array<string>, queryTexts?: array<string>} $options
     */
    public function query(Vector $vector, array $options = []): iterable
    {
        $include = null;
        if ([] !== ($options['include'] ?? [])) {
            $include = array_values(
                array_unique(
                    array_merge(['embeddings', 'metadatas', 'distances'], $options['include'])
                )
            );
        }

        $collection = $this->client->getOrCreateCollection($this->collectionName);
        $queryResponse = $collection->query(
            queryEmbeddings: [$vector->getData()],
            queryTexts: $options['queryTexts'] ?? null,
            nResults: 4,
            where: $options['where'] ?? null,
            whereDocument: $options['whereDocument'] ?? null,
            include: $include,
        );

        $metaCount = \count($queryResponse->metadatas[0]);

        for ($i = 0; $i < $metaCount; ++$i) {
            $metaData = new Metadata($queryResponse->metadatas[0][$i]);
            if (isset($queryResponse->documents[0][$i])) {
                $metaData->setText($queryResponse->documents[0][$i]);
            }

            yield new VectorDocument(
                id: Uuid::fromString($queryResponse->ids[0][$i]),
                vector: new Vector($queryResponse->embeddings[0][$i]),
                metadata: $metaData,
                score: $queryResponse->distances[0][$i] ?? null,
            );
        }
    }
}
