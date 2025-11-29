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
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorizerInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Retriever implements RetrieverInterface
{
    public function __construct(
        private readonly VectorizerInterface $vectorizer,
        private readonly StoreInterface $store,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @return iterable<VectorDocument>
     */
    public function retrieve(string $query, array $options = []): iterable
    {
        $this->logger->debug('Starting document retrieval', ['query' => $query, 'options' => $options]);

        $vector = $this->vectorizer->vectorize($query);

        $this->logger->debug('Query vectorized, searching store');

        $documents = $this->store->query($vector, $options);

        $count = 0;
        foreach ($documents as $document) {
            ++$count;
            yield $document;
        }

        $this->logger->debug('Document retrieval completed', ['retrieved_count' => $count]);
    }
}
