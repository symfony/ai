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
                foreach ($this->dispatchDelta($delta, $this->listeners) as $dispatchedDelta) {
                    yield $dispatchedDelta;
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

    /**
     * @param ListenerInterface[] $listeners
     *
     * @return \Generator<DeltaInterface>
     */
    private function dispatchDelta(DeltaInterface $delta, array $listeners): \Generator
    {
        $event = new DeltaEvent($this, $delta);
        $nestedListeners = $listeners;

        foreach ($listeners as $listener) {
            $previousDelta = $event->getDelta();
            $listener->onDelta($event);

            // A listener that replaced the delta with a generator produced that content itself, so
            // it is left out of the nested dispatch and does not reprocess its own output.
            if ($event->getDelta() !== $previousDelta && $event->getDelta() instanceof \Generator) {
                $nestedListeners = array_filter($nestedListeners, static fn ($nested) => $nested !== $listener);
            }
        }
        $this->getMetadata()->merge($event->getMetadata());

        if ($event->isDeltaSkipped()) {
            return;
        }

        $delta = $event->getDelta();

        if ($delta instanceof DeltaInterface) {
            yield $delta;

            return;
        }

        foreach ($delta as $nestedDelta) {
            foreach ($this->dispatchDelta($nestedDelta, $nestedListeners) as $dispatchedDelta) {
                yield $dispatchedDelta;
            }
        }
    }
}
