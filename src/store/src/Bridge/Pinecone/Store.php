<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone;

use Probots\Pinecone\Client;
use Probots\Pinecone\Resources\Data\VectorResource;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\VectorStoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Store implements VectorStoreInterface
{
    /**
     * @param array<string, mixed> $filter
     */
    public function __construct(
        private Client $pinecone,
        private ?string $namespace = null,
        private array $filter = [],
        private int $topK = 3,
    ) {
        if (!class_exists(Client::class)) {
            throw new RuntimeException('For using the Pinecone as retrieval vector store, the probots-io/pinecone-php package is required. Try running "composer require probots-io/pinecone-php".');
        }
    }

    public function add(VectorDocument ...$documents): void
    {
        $vectors = [];
        foreach ($documents as $document) {
            $vectors[] = [
                'id' => (string) $document->id,
                'values' => $document->vector->getData(),
                'metadata' => $document->metadata->getArrayCopy(),
            ];
        }

        if ([] === $vectors) {
            return;
        }

        $this->getVectors()->upsert($vectors, $this->namespace);
    }

    public function query(Vector $vector, array $options = [], ?float $minScore = null): array
    {
        $result = $this->getVectors()->query(
            vector: $vector->getData(),
            namespace: $options['namespace'] ?? $this->namespace,
            filter: $options['filter'] ?? $this->filter,
            topK: $options['topK'] ?? $this->topK,
            includeValues: true,
        );

        $documents = [];
        foreach ($result->json()['matches'] as $match) {
            $documents[] = new VectorDocument(
                id: Uuid::fromString($match['id']),
                vector: new Vector($match['values']),
                metadata: new Metadata($match['metadata']),
                score: $match['score'],
            );
        }

        return $documents;
    }

    private function getVectors(): VectorResource
    {
        return $this->pinecone->data()->vectors();
    }
}
