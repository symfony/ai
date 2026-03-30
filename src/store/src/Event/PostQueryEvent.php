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
 * Event dispatched after documents are retrieved from the store.
 *
 * Listeners can modify the documents list, for example to rerank results.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PostQueryEvent extends Event
{
    /**
     * @param iterable<VectorDocument> $documents
     * @param array<string, mixed>     $options
     */
    public function __construct(
        private readonly string $query,
        private iterable $documents,
        private readonly array $options = [],
    ) {
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return iterable<VectorDocument>
     */
    public function getDocuments(): iterable
    {
        return $this->documents;
    }

    /**
     * @param iterable<VectorDocument> $documents
     */
    public function setDocuments(iterable $documents): void
    {
        $this->documents = $documents;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
