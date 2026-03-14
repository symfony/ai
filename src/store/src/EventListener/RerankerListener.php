<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\EventListener;

use Symfony\AI\Store\Event\PostQueryEvent;
use Symfony\AI\Store\Reranker\RerankerInterface;

/**
 * Reranks retrieved documents using a cross-encoder model.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RerankerListener
{
    public function __construct(
        private readonly RerankerInterface $reranker,
        private readonly int $topK = 5,
    ) {
    }

    public function __invoke(PostQueryEvent $event): void
    {
        $documents = $event->getDocuments();
        if (!\is_array($documents)) {
            $documents = iterator_to_array($documents);
        }

        $reranked = $this->reranker->rerank(
            $event->getQuery(),
            array_values($documents),
            $event->getOptions()['topK'] ?? $this->topK,
        );

        $event->setDocuments($reranked);
    }
}
