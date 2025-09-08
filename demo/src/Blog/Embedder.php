<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Blog;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\IndexerInterface;

final readonly class Embedder
{
    public function __construct(
        private FeedLoader $loader,
        private IndexerInterface $indexer,
    ) {
    }

    public function embedBlog(): void
    {
        $documents = [];
        foreach ($this->loader->load() as $post) {
            $documents[] = new TextDocument($post->id, $post->toString(), new Metadata($post->toArray()));
        }

        $this->indexer->index($documents);
    }
}
