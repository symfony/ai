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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class Ingester implements IngesterInterface
{
    /**
     * @param string|array<string> $sources
     */
    public function __construct(
        private LoaderInterface $loader,
        private IndexerInterface $indexer,
        private string|array $sources = [],
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->sources = (array) $sources;
    }

    public function withSource(string|array $source): self
    {
        return new self($this->loader, $this->indexer, $source, $this->logger);
    }

    public function ingest(array $options = []): void
    {
        $this->logger->debug('Starting document processing', ['sources' => $this->sources, 'options' => $options]);

        if ($this->sources) {
            $documents = (function () {
                foreach ($this->sources as $singleSource) {
                    yield from $this->loader->load($singleSource);
                }
            })();
        } else {
            $documents = $this->loader->load(null);
        }

        if ([] === $documents) {
            $this->logger->debug('No documents to process', ['sources' => $this->sources]);

            return;
        }

        $this->indexer->index($documents, $options);
    }
}
