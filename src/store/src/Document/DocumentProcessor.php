<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Store\Indexer;

/**
 * Default implementation of DocumentProcessorInterface that orchestrates
 * the complete document processing pipeline: load → transform → vectorize → store.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final readonly class DocumentProcessor implements DocumentProcessorInterface
{
    /**
     * @param TransformerInterface[] $transformers
     */
    public function __construct(
        private LoaderInterface $loader,
        private array $transformers,
        private Indexer $indexer,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function process(string|array $source, array $options = []): void
    {
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
        $this->indexer->index($transformedDocuments, $options['chunk_size'] ?? 50);

        $this->logger->debug('Document processing completed', [
            'source' => $source,
        ]);
    }
}
