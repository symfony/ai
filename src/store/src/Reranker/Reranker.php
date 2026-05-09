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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorDocument;

/**
 * Platform-based reranker that delegates to PlatformInterface for cross-encoder scoring.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Reranker implements RerankerInterface
{
    /**
     * @param non-empty-string $model
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function rerank(string $query, array $documents, int $topK = 5): array
    {
        if ([] === $documents) {
            return [];
        }

        $texts = array_map(
            static fn (VectorDocument $doc): string => $doc->getMetadata()->getText()
                ?? $doc->getMetadata()->getSource() ?? '',
            $documents,
        );

        $this->logger->debug('Reranking {count} documents', ['count' => \count($documents)]);

        $entries = $this->platform
            ->invoke($this->model, ['query' => $query, 'texts' => $texts])
            ->asReranking();

        usort($entries, static fn ($a, $b): int => $b->getScore() <=> $a->getScore());
        $entries = \array_slice($entries, 0, $topK);

        $reranked = [];
        foreach ($entries as $entry) {
            $reranked[] = $documents[$entry->getIndex()]->withScore($entry->getScore());
        }

        $this->logger->debug('Reranking completed, returning {topK} documents', ['topK' => \count($reranked)]);

        return $reranked;
    }
}
