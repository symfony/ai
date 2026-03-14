<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Event;

use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before documents are retrieved from the store.
 *
 * Listeners can modify the query string and options, for example to expand
 * the query, correct spelling, inject synonyms, or adjust options like semanticRatio.
 *
 * Setting documents via setDocuments() short-circuits the retrieval pipeline:
 * the store query is skipped entirely and the provided documents are used instead.
 * PostRetrievalEvent is still dispatched, so reranking/filtering still applies.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PreQueryEvent extends Event
{
    /**
     * @var iterable<VectorDocument>|null
     */
    private ?iterable $documents = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private string $query,
        private array $options = [],
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return iterable<VectorDocument>|null
     */
    public function getDocuments(): ?iterable
    {
        return $this->documents;
    }

    /**
     * Short-circuits the retrieval pipeline by providing documents directly.
     *
     * When documents are set, the store query and vectorization are skipped entirely.
     * PostRetrievalEvent is still dispatched with these documents.
     *
     * @param iterable<VectorDocument> $documents
     */
    public function setDocuments(iterable $documents): void
    {
        $this->documents = $documents;
    }

    public function hasDocuments(): bool
    {
        return null !== $this->documents;
    }
}
