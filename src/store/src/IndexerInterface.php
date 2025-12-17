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

use Symfony\AI\Store\Document\EmbeddableDocumentInterface;

/**
 * Handles the complete document processing pipeline: load → transform → vectorize → store.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface IndexerInterface
{
    /**
     * Process documents through the document pipeline: transform → vectorize → store.
     *
     * @param iterable<EmbeddableDocumentInterface> $documents
     * @param array{chunk_size?: int}               $options   Processing options
     */
    public function index(iterable $documents, array $options = []): void;
}
