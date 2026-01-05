<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Vektor;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Services\Indexer;
use Centamiv\Vektor\Services\Optimizer;
use Centamiv\Vektor\Services\Searcher;
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly string $storagePath,
        private readonly int $dimensions = 1536,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        Config::setDataDir($this->storagePath.'/vektor');
        Config::setDimensions($this->dimensions);

        if ($this->filesystem->exists($this->storagePath.'/vektor')) {
            return;
        }

        $this->filesystem->mkdir($this->storagePath.'/vektor');
    }

    public function drop(array $options = []): void
    {
        if ($options['optimize'] ?? false) {
            $optimizer = new Optimizer();
            $optimizer->run();

            return;
        }

        if (!$this->filesystem->exists($this->storagePath.'/vektor')) {
            return;
        }

        $this->filesystem->remove($this->storagePath.'/vektor');
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $indexer = new Indexer();

        foreach ($documents as $document) {
            $indexer->insert(
                $document->getId(),
                $document->getVector()->getData(),
                $document->getMetadata()->getArrayCopy(),
            );
        }
    }

    public function remove(array|string $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        $indexer = new Indexer();

        foreach ($ids as $id) {
            $indexer->delete($id);
        }
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $searcher = new Searcher();

        $results = $searcher->search($query->getVector()->getData(), $options['k'] ?? 10, true, true);

        foreach ($results as $result) {
            yield $this->convertToVectorDocument($result);
        }
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{
     *     id: string,
     *     score: float,
     *     vector: float[],
     *     metadata: mixed[],
     * } $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists('vector', $data) || null === $data['vector']
            ? new NullVector()
            : new Vector($data['vector']);

        return new VectorDocument($id, $vector, new Metadata($data['metadata']), $data['score'] ?? null);
    }
}
