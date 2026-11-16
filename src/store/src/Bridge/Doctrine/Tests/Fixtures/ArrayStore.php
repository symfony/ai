<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures;

use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\StoreInterface;

/**
 * Keeps the documents by id, so adding a document again replaces it like in a real store.
 */
final class ArrayStore implements StoreInterface
{
    /**
     * @var array<string, VectorDocumentInterface>
     */
    public array $documents = [];

    public ?\Throwable $failure = null;

    public function add(VectorDocumentInterface|array $documents): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        foreach (\is_array($documents) ? $documents : [$documents] as $document) {
            $this->documents[(string) $document->getId()] = $document;
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        foreach (\is_array($ids) ? $ids : [$ids] as $id) {
            unset($this->documents[$id]);
        }
    }

    public function clear(array $options = []): void
    {
        $this->documents = [];
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        return array_values($this->documents);
    }

    public function supports(string $queryClass): bool
    {
        return true;
    }

    public function count(): int
    {
        return \count($this->documents);
    }
}
