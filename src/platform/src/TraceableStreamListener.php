<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\ErrorEvent;
use Symfony\AI\Platform\Result\Stream\StartEvent;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * Records the text of a stream for the profiler, and stops the stopwatch event of its invocation when it ends.
 *
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class TraceableStreamListener extends AbstractStreamListener
{
    /**
     * @param \WeakMap<ResultInterface, string> $resultCache
     * @param \Closure(): void                  $stopEvent
     */
    public function __construct(
        private readonly \WeakMap $resultCache,
        private readonly \Closure $stopEvent,
    ) {
    }

    public function onStart(StartEvent $event): void
    {
        $this->resultCache[$event->getResult()] = '';
    }

    public function onComplete(CompleteEvent $event): void
    {
        $this->record($event->getResult());
    }

    public function onError(ErrorEvent $event): void
    {
        $this->record($event->getResult());
    }

    private function record(StreamResult $result): void
    {
        // the stream collects every delta it yields, including those of a generator that a listener injected
        $this->resultCache[$result] = $result->getAssistantMessage()->asText() ?? '';

        ($this->stopEvent)();
    }
}
