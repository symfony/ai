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

use Symfony\AI\Store\Document\VectorDocument;

/**
 * Retrieves documents from a vector store based on a query string.
 *
 * The opposite of IndexerInterface - while the Indexer loads, transforms, vectorizes and stores documents,
 * the Retriever vectorizes a query and retrieves similar documents from the store.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface RetrieverInterface
{
    /**
     * Retrieve documents from the store that are similar to the given query.
     *
     * @param string               $query   The search query to vectorize and use for similarity search
     * @param array<string, mixed> $options Options to pass to the store query (e.g., limit, filters)
     *
     * @return iterable<VectorDocument> The retrieved documents with similarity scores
     */
    public function retrieve(string $query, array $options = []): iterable;
}
