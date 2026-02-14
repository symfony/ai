<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Result\Stream\ChunkEvent;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\ListenerInterface;
use Symfony\AI\Platform\Result\Stream\StartEvent;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamResult extends BaseResult
{
    /**
     * @param ListenerInterface[] $listeners
     */
    public function __construct(
        private readonly \Generator $generator,
        private array $listeners = [],
    ) {
    }

    public function addListener(ListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function getContent(): \Generator
    {
        $event = new StartEvent($this);
        foreach ($this->listeners as $listener) {
            $listener->onStart($event);
        }
        $this->getMetadata()->merge($event->getMetadata());

        try {
            foreach ($this->generator as $chunk) {
                $event = new ChunkEvent($this, $chunk);
                foreach ($this->listeners as $listener) {
                    $listener->onChunk($event);
                }
                $this->getMetadata()->merge($event->getMetadata());

                if ($event->isChunkSkipped()) {
                    continue;
                }

                $chunk = $event->getChunk();

                if (null === $chunk || !is_iterable($chunk)) {
                    yield $chunk;
                } else {
                    yield from $chunk;
                }
            }
        } finally {
            $event = new CompleteEvent($this);
            foreach ($this->listeners as $listener) {
                $listener->onComplete($event);
            }
            $this->getMetadata()->merge($event->getMetadata());
        }
    }
}
