<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone;

use Probots\Pinecone\Client;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\ManagedStoreInterface;

final class ManagedStore implements ManagedStoreInterface
{
    public function __construct(
        private readonly Client $pinecone,
        private readonly string $indexName,
        private readonly int $dimension,
        private readonly string $metric,
        private readonly string $cloud,
        private readonly string $region,
    ) {
        if (!class_exists(Client::class)) {
            throw new RuntimeException('For using the Pinecone as retrieval vector store, the probots-io/pinecone-php package is required. Try running "composer require probots-io/pinecone-php".');
        }
    }

    public function setup(array $options = []): void
    {
        $this->pinecone
            ->control()
            ->index($this->indexName)
            ->createServerless($this->dimension, $this->metric, $this->cloud, $this->region);
    }

    public function drop(): void
    {
        $this->pinecone
            ->control()
            ->index($this->indexName)
            ->delete();
    }
}
