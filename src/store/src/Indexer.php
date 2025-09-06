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
 */
final readonly class Indexer implements IndexerInterface
{
    /**
     * @param TransformerInterface[] $transformers
     */
    public function __construct(
        private ?LoaderInterface $loader,
        private array $transformers,
        private VectorizerInterface $vectorizer,
        private StoreInterface $store,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param TextDocument|iterable<TextDocument> $documents
     * @param int                                 $chunkSize number of documents to vectorize and store in one batch
     */
    public function index(TextDocument|iterable $documents, int $chunkSize = 50): void
    {
        if ($documents instanceof TextDocument) {
            $documents = [$documents];
        }

        $counter = 0;
        $chunk = [];
        foreach ($documents as $document) {
            $chunk[] = $document;
            ++$counter;

            if ($chunkSize === \count($chunk)) {
                $this->store->add(...$this->vectorizer->vectorize($chunk));
                $chunk = [];
            }
        }

        if (\count($chunk) > 0) {
            $this->store->add(...$this->vectorizer->vectorize($chunk));
        }

        $this->logger->debug(0 === $counter ? 'No documents to index' : \sprintf('Indexed %d documents', $counter));
    }

    /**
     * Process sources through the complete document pipeline: load → transform → vectorize → store.
     * 
     * @param string|array<string> $source  Source identifier (file path, URL, etc.) or array of sources
     * @param array<string, mixed> $options Processing options
     */
    public function __invoke(string|array $source, array $options = []): void
    {
        if (null === $this->loader) {
            throw new \LogicException('Cannot process sources without a loader. Either provide documents directly to index() or configure a loader in the constructor.');
        }

        $this->logger->debug('Starting document processing', [
            'source' => $source,
            'options' => $options,
        ]);

        $sources = (array) $source;
        $allDocuments = [];

        // Load documents from all sources
        foreach ($sources as $singleSource) {
            $documents = ($this->loader)($singleSource, $options['loader'] ?? []);
            foreach ($documents as $document) {
                $allDocuments[] = $document;
            }
        }

        // Transform documents through all transformers
        $transformedDocuments = $allDocuments;
        foreach ($this->transformers as $transformer) {
            $transformedDocuments = ($transformer)($transformedDocuments, $options['transformer'] ?? []);
        }

        // Vectorize and store documents
        $this->index($transformedDocuments, $options['chunk_size'] ?? 50);

        $this->logger->debug('Document processing completed', [
            'source' => $source,
        ]);
    }
}
