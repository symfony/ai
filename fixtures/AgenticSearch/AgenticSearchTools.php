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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Store\Document\Metadata;

/**
 * Agentic search tools for iterative document corpus exploration.
 *
 * Provides four tools that enable an LLM agent to perform multi-hop search:
 * - corpus_search: semantic vector search for broad discovery (results are deduplicated across calls)
 * - corpus_grep: keyword matching on full document content for specific facts
 * - corpus_read_document: full document retrieval by ID
 * - corpus_prune: permanently exclude a document from results and working set
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsTool('corpus_search', description: 'Semantic search over the document corpus. Returns new document snippets ranked by relevance. Already-seen and pruned documents are automatically excluded. Does NOT add documents to the working set - use corpus_read_document for that.', method: 'search')]
#[AsTool('corpus_grep', description: 'Keyword search across full document content. Returns matching lines with line numbers. Use this to find concrete facts like names, dates, or specific terms. Does NOT add documents to the working set - use corpus_read_document for that.', method: 'grep')]
#[AsTool('corpus_read_document', description: 'Read the full content of a document by its ID and add it to the working set. Use after search or grep to investigate a specific document in depth. Only read documents you actually need.', method: 'readDocument')]
#[AsTool('corpus_prune', description: 'Permanently exclude a document from search results and the working set. Use this to discard irrelevant documents. Pruned documents will not appear in future search or grep results.', method: 'prune')]
final class AgenticSearchTools
{
    public function __construct(
        private readonly DocumentCorpus $corpus,
    ) {
    }

    /**
     * @param string $query natural language search query for semantic similarity
     */
    public function search(string $query): string
    {
        $results = $this->corpus->semanticSearch($query);

        if ([] === $results) {
            return \sprintf("No new results found for query: \"%s\" (all matching documents were already seen or pruned)\n\n%s", $query, $this->corpus->formatFoundSummary());
        }

        $output = \sprintf("Search results for \"%s\":\n\n", $query);

        foreach ($results as $doc) {
            $id = (string) $doc->getId();
            $title = $this->resolveTitle($doc->getMetadata());
            $text = $doc->getMetadata()->getText() ?? '';
            $snippet = \strlen($text) > 200 ? substr($text, 0, 200).'...' : $text;
            $score = $doc->getScore();

            $output .= \sprintf("- [ID: %s] %s (relevance: %.2f)\n  %s\n\n", $id, $title, $score ?? 0.0, $snippet);
        }

        $output .= \sprintf("\nUse corpus_read_document with a document ID to add it to your working set.\n\n%s", $this->corpus->formatFoundSummary());

        return $output;
    }

    /**
     * @param string $pattern text pattern or keyword to search for in document content
     */
    public function grep(string $pattern): string
    {
        $results = $this->corpus->grepDocuments($pattern);

        if ([] === $results) {
            return \sprintf("No matches found for pattern: \"%s\"\n\n%s", $pattern, $this->corpus->formatFoundSummary());
        }

        $output = \sprintf("Grep results for \"%s\":\n\n", $pattern);

        foreach ($results as $id => $result) {
            $matchLines = \array_slice($result['matches'], 0, 5);
            $output .= \sprintf("[ID: %s] %s\n%s\n\n", $id, $result['title'], implode("\n", $matchLines));

            if (\count($result['matches']) > 5) {
                $output .= \sprintf("  ... and %d more matches\n\n", \count($result['matches']) - 5);
            }
        }

        $output .= \sprintf("\nUse corpus_read_document with a document ID to add it to your working set.\n\n%s", $this->corpus->formatFoundSummary());

        return $output;
    }

    /**
     * @param string $documentId the document ID obtained from search or grep results
     */
    public function readDocument(string $documentId): string
    {
        if ($this->corpus->isPruned($documentId)) {
            return \sprintf("Document %s has been pruned and is no longer available.\n\n%s", $documentId, $this->corpus->formatFoundSummary());
        }

        $doc = $this->corpus->getOriginalDocument($documentId);

        if (null === $doc) {
            return \sprintf('No document found with ID: %s', $documentId);
        }

        $title = $this->resolveTitle($doc->getMetadata());

        $content = $doc->getContent();
        $snippet = \strlen($content) > 100 ? substr($content, 0, 100).'...' : $content;

        $this->corpus->addToFound($documentId, $title, $snippet);

        return \sprintf(
            "=== Document: %s (ID: %s) ===\n\n%s\n\n%s",
            $title,
            $documentId,
            $doc->getContent(),
            $this->corpus->formatFoundSummary(),
        );
    }

    /**
     * @param string $documentId the document ID to permanently exclude
     */
    public function prune(string $documentId): string
    {
        $this->corpus->pruneDocument($documentId);

        return \sprintf("Pruned document %s. It will be excluded from all future search and grep results.\n\n%s", $documentId, $this->corpus->formatFoundSummary());
    }

    /**
     * Resolves the document title from metadata using the typed accessor.
     */
    private function resolveTitle(Metadata $metadata): string
    {
        return $metadata->getTitle() ?? 'Unknown';
    }
}
