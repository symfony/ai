<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures\AgenticSearch;

use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * Shared state for agentic search tools.
 *
 * Manages the vector store, original documents, deduplication of search results,
 * pruning state, and the "found documents" working set.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DocumentCorpus
{
    /**
     * @var array<string, array{id: string, title: string, snippet: string}>
     */
    private array $foundDocuments = [];

    /**
     * @var array<string, true>
     */
    private array $seenDocumentIds = [];

    /**
     * @var array<string, true>
     */
    private array $prunedDocumentIds = [];

    /**
     * @param array<string, TextDocument> $originalDocuments map of document ID to original TextDocument
     */
    public function __construct(
        private readonly VectorizerInterface $vectorizer,
        private readonly StoreInterface $store,
        private readonly array $originalDocuments,
    ) {
    }

    /**
     * Semantic vector search, filtering out already-seen and pruned documents.
     *
     * @param int $maxResults maximum number of new results to return
     *
     * @return VectorDocument[]
     */
    public function semanticSearch(string $query, int $maxResults = 5): array
    {
        if ($maxResults <= 0) {
            return [];
        }

        $vector = $this->vectorizer->vectorize($query);
        $excludedCount = \count($this->seenDocumentIds) + \count($this->prunedDocumentIds);
        $results = iterator_to_array($this->store->query(new VectorQuery($vector), ['maxItems' => $maxResults + $excludedCount]));

        $filtered = [];
        foreach ($results as $doc) {
            $id = (string) $doc->getId();
            if (isset($this->seenDocumentIds[$id]) || isset($this->prunedDocumentIds[$id])) {
                continue;
            }
            $this->seenDocumentIds[$id] = true;
            $filtered[] = $doc;

            if (\count($filtered) >= $maxResults) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Grep: case-insensitive keyword search across original document content.
     *
     * Unlike semantic search, this performs exact substring matching on the full
     * document text, returning matching lines with context. Pruned documents
     * are excluded.
     *
     * @return array<string, array{title: string, matches: string[]}>
     */
    public function grepDocuments(string $pattern): array
    {
        if ('' === $pattern) {
            return [];
        }

        $results = [];

        foreach ($this->originalDocuments as $id => $doc) {
            if (isset($this->prunedDocumentIds[$id])) {
                continue;
            }

            $lines = explode("\n", $doc->getContent());
            $matches = [];

            foreach ($lines as $lineNum => $line) {
                // Strip markdown formatting (bold, italic, links) for matching
                $plainLine = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $line) ?? $line;
                if (false !== stripos($plainLine, $pattern)) {
                    $matches[] = \sprintf('  L%d: %s', $lineNum + 1, trim($line));
                }
            }

            if ([] !== $matches) {
                $results[$id] = [
                    'title' => $doc->getMetadata()->getTitle() ?? 'Unknown',
                    'matches' => $matches,
                ];
            }
        }

        return $results;
    }

    public function getOriginalDocument(string $id): ?TextDocument
    {
        return $this->originalDocuments[$id] ?? null;
    }

    public function isPruned(string $id): bool
    {
        return isset($this->prunedDocumentIds[$id]);
    }

    public function addToFound(string $id, string $title, string $snippet): void
    {
        if (isset($this->prunedDocumentIds[$id])) {
            return;
        }

        $this->foundDocuments[$id] = [
            'id' => $id,
            'title' => $title,
            'snippet' => $snippet,
        ];
    }

    /**
     * Prunes a document: removes from working set and excludes from future searches.
     */
    public function pruneDocument(string $id): void
    {
        $this->prunedDocumentIds[$id] = true;
        unset($this->foundDocuments[$id]);
    }

    /**
     * @return array<string, array{id: string, title: string, snippet: string}>
     */
    public function getFoundDocuments(): array
    {
        return $this->foundDocuments;
    }

    public function formatFoundSummary(): string
    {
        $count = \count($this->foundDocuments);

        if (0 === $count) {
            return 'Working set: empty (no documents found yet)';
        }

        $lines = [\sprintf('Working set (%d documents):', $count)];
        foreach ($this->foundDocuments as $doc) {
            $lines[] = \sprintf('  - [%s] %s', $doc['id'], $doc['title']);
        }

        return implode("\n", $lines);
    }
}
