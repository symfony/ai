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

use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\AI\Platform\Result\Stream\ErrorEvent;
use Symfony\AI\Platform\Result\Stream\ListenerInterface;
use Symfony\AI\Platform\Result\Stream\StartEvent;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamResult extends BaseResult
{
    /**
     * @param \Generator<DeltaInterface> $generator
     * @param ListenerInterface[]        $listeners
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

    /**
     * @return ListenerInterface[]
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /**
     * @return \Generator<DeltaInterface>
     */
    public function getContent(): \Generator
    {
        $event = new StartEvent($this);
        foreach ($this->listeners as $listener) {
            $listener->onStart($event);
        }
        $this->getMetadata()->merge($event->getMetadata());

        try {
            foreach ($this->generator as $delta) {
                $event = new DeltaEvent($this, $delta);
                foreach ($this->listeners as $listener) {
                    $listener->onDelta($event);
                }
                $this->getMetadata()->merge($event->getMetadata());

                if ($event->isDeltaSkipped()) {
                    continue;
                }

                $delta = $event->getDelta();

                if ($delta instanceof DeltaInterface) {
                    yield $delta;
                } else {
                    yield from $delta;
                }
            }
        } catch (\Throwable $e) {
            // Notify listeners before rethrowing (see ErrorEvent); an abandoned stream breaks out
            // and tears the generator down without entering this catch, so it stays unbilled.
            $event = new ErrorEvent($this, $e);
            foreach ($this->listeners as $listener) {
                $listener->onError($event);
            }
            $this->getMetadata()->merge($event->getMetadata());

            throw $e;
        }

        $event = new CompleteEvent($this);
        foreach ($this->listeners as $listener) {
            $listener->onComplete($event);
        }
        $this->getMetadata()->merge($event->getMetadata());
    }
}
