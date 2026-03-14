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

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Event\PostQueryEvent;
use Symfony\AI\Store\Event\PreQueryEvent;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Retriever implements RetrieverInterface
{
    public function __construct(
        private readonly StoreInterface $store,
        private readonly ?VectorizerInterface $vectorizer = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return iterable<VectorDocument>
     */
    public function retrieve(string $query, array $options = []): iterable
    {
        $this->logger->debug('Starting document retrieval', ['query' => $query, 'options' => $options]);

        $documents = null;
        if (null !== $this->eventDispatcher) {
            [$query, $options, $documents] = $this->dispatchPreRetrieval($query, $options);
        }

        if (null === $documents) {
            $queryObject = $this->createQuery($query, $options);

            $this->logger->debug('Searching store', ['query_type' => $queryObject::class]);

            $documents = $this->store->query($queryObject, $options);
        } else {
            $this->logger->debug('Using documents from PreQueryEvent, skipping store query');
        }

        if (null !== $this->eventDispatcher) {
            $documents = $this->dispatchPostRetrieval($query, $documents, $options);
        }

        return $this->yieldDocuments($documents);
    }

    /**
     * @param iterable<VectorDocument> $documents
     *
     * @return \Generator<VectorDocument>
     */
    private function yieldDocuments(iterable $documents): \Generator
    {
        $count = 0;
        foreach ($documents as $document) {
            ++$count;
            yield $document;
        }

        $this->logger->debug('Document retrieval completed', ['retrieved_count' => $count]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{string, array<string, mixed>, iterable<VectorDocument>|null}
     */
    private function dispatchPreRetrieval(string $query, array $options): array
    {
        $event = new PreQueryEvent($query, $options);
        $this->eventDispatcher?->dispatch($event);

        return [$event->getQuery(), $event->getOptions(), $event->getDocuments()];
    }

    /**
     * @param iterable<VectorDocument> $documents
     * @param array<string, mixed>     $options
     *
     * @return iterable<VectorDocument>
     */
    private function dispatchPostRetrieval(string $query, iterable $documents, array $options): iterable
    {
        $event = new PostQueryEvent($query, $documents, $options);
        $this->eventDispatcher?->dispatch($event);

        return $event->getDocuments();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createQuery(string $query, array $options): QueryInterface
    {
        if (null === $this->vectorizer) {
            $this->logger->debug('No vectorizer configured, using TextQuery if supported');

            return new TextQuery($query);
        }

        if (!$this->store->supports(VectorQuery::class)) {
            $this->logger->debug('Store does not support vector queries, falling back to TextQuery');

            return new TextQuery($query);
        }

        if ($this->store->supports(HybridQuery::class)) {
            $this->logger->debug('Store supports hybrid queries, using HybridQuery with semantic ratio', ['semanticRatio' => $options['semanticRatio'] ?? 0.5]);

            return new HybridQuery($this->vectorizer->vectorize($query), $query, $options['semanticRatio'] ?? 0.5);
        }

        $this->logger->debug('Store supports vector queries, using VectorQuery');

        return new VectorQuery($this->vectorizer->vectorize($query));
    }
}
