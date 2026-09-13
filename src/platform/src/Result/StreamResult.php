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

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Result\Stream\AssistantMessageStreamListener;
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
    private readonly AssistantMessageStreamListener $assistantMessage;

    /**
     * Weak on purpose: the generator holds the result, so a strong reference would make the pair
     * a cycle and defer freeing the underlying HTTP response to the cycle collector.
     *
     * @var \WeakReference<\Generator<DeltaInterface>>|null
     */
    private ?\WeakReference $stream = null;

    private bool $started = false;

    private bool $finished = false;

    private bool $consuming = false;

    /**
     * @param \Generator<DeltaInterface> $generator
     * @param ListenerInterface[]        $listeners
     */
    public function __construct(
        private readonly \Generator $generator,
        private array $listeners = [],
    ) {
        $this->assistantMessage = new AssistantMessageStreamListener();
    }

    /**
     * The assistant turn this stream carried, draining what the caller has not read.
     *
     * Two cases return the turn as collected so far rather than a complete one: reading it from
     * inside the stream - a listener's onComplete(), say - because the generator cannot be resumed
     * while its body is running, and reading it after a partial iteration whose generator is gone,
     * because a fresh one would re-read the delta the abandoned one stopped on. Keep the generator
     * in a variable to have an abandoned stream finished here.
     *
     * Do not call this from inside a loop over the stream: a suspended generator is indistinguishable
     * from an abandoned one, so the rest of the turn is drained and the loop ends on the next delta.
     * Attach an {@see AssistantMessageStreamListener} to read the turn mid-stream.
     */
    public function getAssistantMessage(): AssistantMessage
    {
        $stream = $this->stream?->get();

        if (!$this->consuming && !$this->finished && (null !== $stream || !$this->started)) {
            $stream ??= $this->consume();

            while ($stream->valid()) {
                $stream->next();
            }
        }

        return $this->assistantMessage->getAssistantMessage();
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
     * The deltas, from where the last consumer left off: the stream is read once, so a generator
     * taken after it finished yields nothing and two iterated in parallel split the deltas.
     *
     * @return \Generator<DeltaInterface>
     */
    public function getContent(): \Generator
    {
        $stream = $this->consume();
        $this->stream = \WeakReference::create($stream);

        return $stream;
    }

    /**
     * @return \Generator<DeltaInterface>
     */
    private function consume(): \Generator
    {
        if ($this->finished) {
            return;
        }

        $this->consuming = true;

        // resuming an abandoned stream continues the turn it collected
        if (!$this->started) {
            $this->started = true;
            $this->assistantMessage->reset();

            $event = new StartEvent($this);
            foreach ($this->listeners as $listener) {
                $listener->onStart($event);
            }
            $this->getMetadata()->merge($event->getMetadata());
        }

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

                $emitted = $event->getDelta();

                foreach ($emitted instanceof DeltaInterface ? [$emitted] : $emitted as $inner) {
                    $this->assistantMessage->accumulate($inner);

                    // clear across the yield: suspended, the generator can be resumed and an
                    // abandoned stream drained; resuming it while the body runs is fatal
                    $this->consuming = false;

                    yield $inner;

                    $this->consuming = true;
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

            $this->finished = true;
            $this->consuming = false;

            throw $e;
        }

        $event = new CompleteEvent($this);
        foreach ($this->listeners as $listener) {
            $listener->onComplete($event);
        }
        $this->getMetadata()->merge($event->getMetadata());

        $this->finished = true;

        // cleared after the listeners: they run inside this generator and must not resume it
        $this->consuming = false;
    }
}
