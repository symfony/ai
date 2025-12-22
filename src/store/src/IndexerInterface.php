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
 * Handles the complete document processing pipeline: load → transform → vectorize → store.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface IndexerInterface
{
    /**
     * Process sources through the complete document pipeline: load → transform → vectorize → store.
     *
     * @param array{chunk_size?: int, platform_options?: array<string, mixed>} $options Processing options
     */
    public function index(array $options = []): void;

    /**
     * Create a new instance with a different source.
     *
     * @param string|array<string> $source Source identifier (file path, URL, etc.) or array of sources
     */
    public function withSource(string|array $source): self;
}
