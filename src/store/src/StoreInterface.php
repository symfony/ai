<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store;

use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\QueryInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface StoreInterface extends \Countable
{
    /**
     * Adds documents to the store, so they can be queried afterwards.
     *
     * @param VectorDocumentInterface|VectorDocumentInterface[] $documents
     */
    public function add(VectorDocumentInterface|array $documents): void;

    /**
     * Removes the documents with the given ids from the store.
     *
     * @param string|array<string> $ids
     * @param array<string, mixed> $options
     */
    public function remove(string|array $ids, array $options = []): void;

    /**
     * Removes all documents from the store, but keeps the store usable.
     *
     * @param array<string, mixed> $options
     */
    public function clear(array $options = []): void;

    /**
     * Returns the document with the given id, with its vector, or null when the store has none.
     */
    public function find(string $id): ?VectorDocumentInterface;

    /**
     * Returns the documents nearest to the one with the given id, leaving that document out.
     *
     * The options are the ones of a vector query, and the "limit" option does not count the document itself.
     * There are no similar documents to a document the store does not have.
     *
     * @param array<string, mixed> $options
     *
     * @return iterable<VectorDocumentInterface>
     *
     * @throws UnsupportedQueryTypeException if the store does not support vector queries
     */
    public function findSimilar(string $id, array $options = []): iterable;

    /**
     * Returns the documents of the store matching the given query.
     *
     * The "limit" option caps the number of returned documents.
     *
     * @param array<string, mixed> $options
     *
     * @return iterable<VectorDocumentInterface>
     *
     * @throws UnsupportedQueryTypeException if query type not supported
     */
    public function query(QueryInterface $query, array $options = []): iterable;

    /**
     * Tells whether the store supports the given query type.
     *
     * @param class-string<QueryInterface> $queryClass The query class to check
     */
    public function supports(string $queryClass): bool;
}
