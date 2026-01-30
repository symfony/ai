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

/**
 * Handles the complete document processing pipeline: load/accept → filter → transform → vectorize → store.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface IndexerInterface
{
    /**
     * Process input through the complete document pipeline.
     *
     * @param string|iterable<string|object>|object                            $input   Input to process (source identifier, documents, etc.)
     * @param array{chunk_size?: int, platform_options?: array<string, mixed>} $options Processing options
     */
    public function index(string|iterable|object $input, array $options = []): void;
}
