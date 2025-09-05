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
 * High-level interface for processing documents through the complete pipeline:
 * load → transform → vectorize → store.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface DocumentProcessorInterface
{
    /**
     * Process a source through the complete indexing pipeline.
     *
     * @param string|array<string> $source  Source identifier (file path, URL, etc.) or array of sources
     * @param array<string, mixed> $options Processing options
     */
    public function process(string|array $source, array $options = []): void;

}
