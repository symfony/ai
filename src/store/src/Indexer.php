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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\FilterInterface;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Exception\RuntimeException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class Indexer implements IndexerInterface
{
    /**
     * @param FilterInterface[]      $filters      Filters to apply after loading documents to remove unwanted content
     * @param TransformerInterface[] $transformers Transformers to mutate documents after filtering (chunking, cleaning, etc.)
     */
    public function __construct(
        private VectorizerInterface $vectorizer,
        private StoreInterface $store,
        private ?LoaderInterface $loader = null,
        private array $filters = [],
        private array $transformers = [],
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function index(string|array|EmbeddableDocumentInterface|null $source = null, array $options = []): void
    {
        $documents = $this->resolveDocuments($source);

        if ([] === $documents) {
            $this->logger->debug('No documents to process');

            return;
        }

        foreach ($this->filters as $filter) {
            $documents = $filter->filter($documents);
        }

        foreach ($this->transformers as $transformer) {
            $documents = $transformer->transform($documents);
        }

        $chunkSize = $options['chunk_size'] ?? 50;
        $counter = 0;
        $chunk = [];
        foreach ($documents as $document) {
            $chunk[] = $document;
            ++$counter;

            if ($chunkSize === \count($chunk)) {
                $this->store->add($this->vectorizer->vectorize($chunk, $options['platform_options'] ?? []));
                $chunk = [];
            }
        }

        if ([] !== $chunk) {
            $this->store->add($this->vectorizer->vectorize($chunk, $options['platform_options'] ?? []));
        }

        $this->logger->debug('Document processing completed', ['total_documents' => $counter]);
    }

    /**
     * Resolve the source parameter into an array of documents.
     *
     * @param string|array<string>|EmbeddableDocumentInterface|array<EmbeddableDocumentInterface>|null $source
     *
     * @return EmbeddableDocumentInterface[]
     */
    private function resolveDocuments(string|array|EmbeddableDocumentInterface|null $source): array
    {
        // Direct document(s) passed - no loader needed
        if ($source instanceof EmbeddableDocumentInterface) {
            $this->logger->debug('Processing single document directly');

            return [$source];
        }

        // Check if array contains documents or sources (or is empty)
        if (\is_array($source)) {
            // Empty array - no documents to process, no loader needed
            if ([] === $source) {
                $this->logger->debug('Empty document array provided');

                return [];
            }

            $firstElement = reset($source);
            if ($firstElement instanceof EmbeddableDocumentInterface) {
                $this->logger->debug('Processing document array directly', ['count' => \count($source)]);

                return $this->filterDocuments($source);
            }

            // Array contains strings - will be processed by the loader below
            // We know $firstElement is string here since it's not EmbeddableDocumentInterface
            \assert(\is_string($firstElement));
            $stringSources = array_filter($source, 'is_string');

            return $this->loadFromSources($stringSources);
        }

        // Source string or null - loader is required
        return $this->loadFromSources(null === $source ? [null] : [$source]);
    }

    /**
     * Filter array to only include EmbeddableDocumentInterface instances.
     *
     * @param array<mixed> $source
     *
     * @return EmbeddableDocumentInterface[]
     */
    private function filterDocuments(array $source): array
    {
        $documents = [];
        foreach ($source as $item) {
            if ($item instanceof EmbeddableDocumentInterface) {
                $documents[] = $item;
            }
        }

        return $documents;
    }

    /**
     * Load documents from source strings using the loader.
     *
     * @param array<string|null> $sources
     *
     * @return EmbeddableDocumentInterface[]
     */
    private function loadFromSources(array $sources): array
    {
        if (null === $this->loader) {
            throw new RuntimeException('A loader is required when indexing from sources. Either provide a loader in the constructor or pass documents directly.');
        }

        $this->logger->debug('Loading documents from sources', ['sources' => $sources]);

        $documents = [];
        foreach ($sources as $singleSource) {
            $documents = array_merge($documents, $this->loadSource($singleSource));
        }

        return $documents;
    }

    /**
     * @return EmbeddableDocumentInterface[]
     */
    private function loadSource(?string $source): array
    {
        \assert(null !== $this->loader);

        $documents = [];
        foreach ($this->loader->load($source) as $document) {
            $documents[] = $document;
        }

        return $documents;
    }
}
