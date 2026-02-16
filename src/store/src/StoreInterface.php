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

use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\QueryInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface StoreInterface
{
    /**
     * @param VectorDocument|VectorDocument[] $documents
     */
    public function add(VectorDocument|array $documents): void;

    /**
     * @param string|array<string> $ids
     * @param array<string, mixed> $options
     */
    public function remove(string|array $ids, array $options = []): void;

    /**
     * @param array<string, mixed> $options
     *
     * @return iterable<VectorDocument>
     *
     * @throws UnsupportedQueryTypeException if query type not supported
     */
    public function query(QueryInterface $query, array $options = []): iterable;

    /**
     * @param class-string<QueryInterface> $queryClass The query class to check
     */
    public function supports(string $queryClass): bool;
}
