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
use Symfony\AI\Store\Event\PreQueryEvent;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Reranker\RerankerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reranks retrieved documents using a cross-encoder model.
 *
 * Registered on PreQueryEvent as well, it fetches "limit" times the over-fetch factor documents from the store.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RerankerListener implements ResetInterface
{
    private ?int $requestedLimit = null;

    /**
     * @param positive-int $overFetchFactor
     */
    public function __construct(
        private readonly RerankerInterface $reranker,
        private readonly int $limit = 5,
        private readonly int $overFetchFactor = 1,
    ) {
        if ($overFetchFactor < 1) {
            throw new InvalidArgumentException(\sprintf('The over-fetch factor must be at least 1, %d given.', $overFetchFactor));
        }
    }

    public function __invoke(PreQueryEvent|PostQueryEvent $event): void
    {
        if ($event instanceof PreQueryEvent) {
            $this->overFetch($event);

            return;
        }

        $limit = $this->requestedLimit ?? $event->getOptions()['limit'] ?? $this->limit;
        $this->requestedLimit = null;

        $documents = $event->getDocuments();
        if (!\is_array($documents)) {
            $documents = iterator_to_array($documents);
        }

        $reranked = $this->reranker->rerank(
            $event->getQuery(),
            array_values($documents),
            $limit,
        );

        $event->setDocuments($reranked);
    }

    public function reset(): void
    {
        $this->requestedLimit = null;
    }

    private function overFetch(PreQueryEvent $event): void
    {
        $options = $event->getOptions();
        $this->requestedLimit = $options['limit'] ?? $this->limit;
        $options['limit'] = $this->requestedLimit * $this->overFetchFactor;

        $event->setOptions($options);
    }
}
