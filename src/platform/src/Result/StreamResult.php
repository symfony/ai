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
use Symfony\AI\Platform\Result\Stream\ListenerInterface;
use Symfony\AI\Platform\Result\Stream\StartEvent;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamResult extends BaseResult implements CancellableInterface
{
    private bool $cancelled = false;

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
     * @return \Generator<DeltaInterface>
     */
    public function getContent(): \Generator
    {
        $startEmitted = false;

        try {
            foreach ($this->generator as $delta) {
                if ($this->cancelled) {
                    return;
                }

                if (!$startEmitted) {
                    $startEvent = new StartEvent($this);
                    foreach ($this->listeners as $listener) {
                        $listener->onStart($startEvent);
                    }
                    $this->getMetadata()->merge($startEvent->getMetadata());
                    $startEmitted = true;

                    if ($startEvent->isStopRequested()) {
                        $this->cancel();

                        return;
                    }
                }

                $event = new DeltaEvent($this, $delta);
                foreach ($this->listeners as $listener) {
                    $listener->onDelta($event);
                }
                $this->getMetadata()->merge($event->getMetadata());

                if ($event->isStopRequested()) {
                    $this->cancel();

                    return;
                }

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
        } catch (TransportExceptionInterface $e) {
            if ($this->cancelled) {
                return;
            }

            throw $e;
        }

        if ($this->cancelled) {
            return;
        }

        $completeEvent = new CompleteEvent($this);
        foreach ($this->listeners as $listener) {
            $listener->onComplete($completeEvent);
        }
        $this->getMetadata()->merge($completeEvent->getMetadata());
    }

    public function cancel(): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;

        if ($this->rawResult instanceof CancellableInterface) {
            $this->rawResult->cancel();
        }
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
