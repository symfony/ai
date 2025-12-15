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

use Symfony\AI\Store\Document\SourceInterface;

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
     * @param SourceInterface|SourceInterface[] $sources Document sources to process
     * @param array{chunk_size?: int, platform_options?: array<string, mixed>} $options Processing options
     */
    public function index(array|SourceInterface $sources, array $options = []): void;
}
