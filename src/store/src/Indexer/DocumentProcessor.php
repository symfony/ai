<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Indexer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\FilterInterface;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\StoreInterface;

/**
 * Internal service that handles the shared document processing pipeline.
 *
 * Pipeline: filter → transform → vectorize → store
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class DocumentProcessor
{
    /**
     * @param FilterInterface[]      $filters      Filters to apply to remove unwanted documents
     * @param TransformerInterface[] $transformers Transformers to mutate documents (chunking, cleaning, etc.)
     */
    public function __construct(
        private VectorizerInterface $vectorizer,
        private StoreInterface $store,
        private array $filters = [],
        private array $transformers = [],
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Process documents through the pipeline: filter → transform → vectorize → store.
     *
     * @param iterable<EmbeddableDocumentInterface>                            $documents Documents to process
     * @param array{chunk_size?: int, platform_options?: array<string, mixed>} $options   Processing options
     */
    public function process(iterable $documents, array $options = []): void
    {
        $this->logger->debug('Starting document processing pipeline');

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
            if (!$document instanceof EmbeddableDocumentInterface) {
                throw new InvalidArgumentException(\sprintf('DocumentProcessor expects documents to be instances of EmbeddableDocumentInterface, got "%s".', get_debug_type($document)));
            }

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
}
