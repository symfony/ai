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
use Symfony\AI\Store\Document\FilterInterface;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Document\VectorizerInterface;

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
        private LoaderInterface $loader,
        private VectorizerInterface $vectorizer,
        private StoreInterface $store,
        private array $filters = [],
        private array $transformers = [],
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function index(string|iterable|null $source = null, array $options = []): void
    {
        $sources = match (true) {
            is_iterable($source) => $source,
            default => [$source],
        };

        $this->logger->debug('Starting document processing', ['sources' => $sources, 'options' => $options]);

        $documents = (function () use ($sources) {
            foreach ($sources as $singleSource) {
                yield from $this->loader->load($singleSource);
            }
        })();

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
}
