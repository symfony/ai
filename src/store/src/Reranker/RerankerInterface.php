<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Reranker;

use Symfony\AI\Store\Document\VectorDocument;

/**
 * Reranks a list of retrieved documents using a cross-encoder model.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface RerankerInterface
{
    /**
     * Rerank documents for the given query.
     *
     * @param list<VectorDocument> $documents
     *
     * @return list<VectorDocument> Reranked documents with updated scores, limited to $topK
     */
    public function rerank(string $query, array $documents, int $topK = 5): array;
}
