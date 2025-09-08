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

/**
 * Converts a collection of TextDocuments into VectorDocuments and pushes them to a store implementation.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface IndexerInterface
{
    /**
     * @param TextDocument|iterable<TextDocument> $documents
     * @param int                                 $chunkSize number of documents to vectorize and store in one batch
     */
    public function index(TextDocument|iterable $documents, int $chunkSize = 50): void;
}
