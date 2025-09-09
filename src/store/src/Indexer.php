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
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Document\VectorizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class Indexer implements IndexerInterface
{
    /**
     * @var array<string|null>
     */
    private array $sources = [];

    /**
     * @param string|array<string>|null $source
     * @param TransformerInterface[]    $transformers
     */
    public function __construct(
        private LoaderInterface $loader,
        private VectorizerInterface $vectorizer,
        private StoreInterface $store,
        string|array|null $source = null,
        private array $transformers = [],
        private ?string $description = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->sources = null === $source ? [] : (array) $source;
    }

    public function withSource(string|array $source): self
    {
        return new self($this->loader, $this->vectorizer, $this->store, $source, $this->transformers, $this->description, $this->logger);
    }

    public function index(array $options = []): void
    {
        $this->logger->debug('Starting document processing', ['sources' => $this->sources, 'options' => $options]);

        $documents = [];
        if ([] === $this->sources) {
            // No specific source provided, load with null
            $documents = $this->loadSource(null);
        } else {
            foreach ($this->sources as $singleSource) {
                $documents = array_merge($documents, $this->loadSource($singleSource));
            }
        }

        if ([] === $documents) {
            $this->logger->debug('No documents to process', ['sources' => $this->sources]);

            return;
        }

        // Transform documents through all transformers
        foreach ($this->transformers as $transformer) {
            $documents = $transformer->transform($documents);
        }

        // Vectorize and store documents in chunks
        $chunkSize = $options['chunk_size'] ?? 50;
        $counter = 0;
        $chunk = [];
        foreach ($documents as $document) {
            $chunk[] = $document;
            ++$counter;

            if ($chunkSize === \count($chunk)) {
                $this->store->add(...$this->vectorizer->vectorizeTextDocuments($chunk));
                $chunk = [];
            }
        }

        // Handle remaining documents
        if ([] !== $chunk) {
            $this->store->add(...$this->vectorizer->vectorizeTextDocuments($chunk));
        }

        $this->logger->debug('Document processing completed', ['total_documents' => $counter]);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return TextDocument[]
     */
    private function loadSource(?string $source): array
    {
        $documents = [];
        foreach ($this->loader->load($source) as $document) {
            $documents[] = $document;
        }

        return $documents;
    }
}
