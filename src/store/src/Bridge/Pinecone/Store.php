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
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param array<string, mixed> $filter
     */
    public function __construct(
        private readonly Client $pinecone,
        private readonly string $indexName,
        private readonly ?string $namespace = null,
        private readonly array $filter = [],
        private readonly int $topK = 3,
    ) {
    }

    /**
     * @param array{
     *     dimension?: int,
     *     metric?: string,
     *     cloud?: string,
     *     region?: string,
     * } $options
     */
    public function setup(array $options = []): void
    {
        if (false === isset($options['dimension'])) {
            throw new InvalidArgumentException('The "dimension" option is required.');
        }

        $this->pinecone
            ->control()
            ->index($this->indexName)
            ->createServerless(
                $options['dimension'],
                $options['metric'] ?? null,
                $options['cloud'] ?? null,
                $options['region'] ?? null,
            );
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

    public function query(Vector $vector, array $options = []): iterable
    {
        $result = $this->getVectors()->query(
            vector: $vector->getData(),
            namespace: $options['namespace'] ?? $this->namespace,
            filter: $options['filter'] ?? $this->filter,
            topK: $options['topK'] ?? $this->topK,
            includeValues: true,
        );

        foreach ($result->json()['matches'] as $match) {
            yield new VectorDocument(
                id: Uuid::fromString($match['id']),
                vector: new Vector($match['values']),
                metadata: new Metadata($match['metadata']),
                score: $match['score'],
            );
        }
    }

    public function drop(array $options = []): void
    {
        $this->pinecone
            ->control()
            ->index($this->indexName)
            ->delete();
    }

    private function getVectors(): VectorResource
    {
        return $this->pinecone->data()->vectors();
    }
}
