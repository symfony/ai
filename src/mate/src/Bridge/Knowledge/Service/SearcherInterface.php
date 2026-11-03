<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Service;

use Symfony\AI\Mate\Bridge\Knowledge\Model\PageChunk;

/**
 * Extension seam for swapping the search strategy used by `knowledge-search`.
 *
 * The bridge ships a {@see KeywordSearcher} that performs case-insensitive
 * substring matching. A future implementation could plug in semantic search
 * powered by {@see \Symfony\AI\Store\Document\Vectorizer} without touching
 * the `SearchTool` capability.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface SearcherInterface
{
    /**
     * @param list<PageChunk> $chunks
     *
     * @return list<array{path: string, page_title: string, section_title: string, score: int|float, snippet: string}>
     */
    public function search(array $chunks, string $query, int $limit = 20): array;
}
