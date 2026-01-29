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
     * Process sources or documents through the complete document pipeline: (load →) filter → transform → vectorize → store.
     *
     * When passing documents directly, the loader is bypassed.
     *
     * @param string|array<string>|EmbeddableDocumentInterface|array<EmbeddableDocumentInterface>|null $source  Source identifier (file path, URL, etc.), array of sources, or document(s) to index directly
     * @param array{chunk_size?: int, platform_options?: array<string, mixed>}                         $options Processing options
     */
    public function index(string|array|EmbeddableDocumentInterface|null $source = null, array $options = []): void;
}
