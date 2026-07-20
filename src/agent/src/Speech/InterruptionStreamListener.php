<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Speech;

use Symfony\AI\Platform\Result\Exception\InterruptedException;
use Symfony\AI\Platform\Result\InterruptionSignalInterface;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\AI\Platform\Result\Stream\ListenerInterface;
use Symfony\AI\Platform\Result\Stream\StartEvent;

/**
 * Cooperative stream listener that aborts a TTS stream when an
 * {@see InterruptionSignalInterface} is fired between deltas.
 *
 * Attached internally by {@see \Symfony\AI\Agent\SpeechAgent} to the TTS
 * `StreamResult` when an `interruption_signal` option is provided. Throws
 * {@see InterruptedException} from `onStart()` / `onDelta()` as soon as the
 * signal is observed; the exception propagates through the stream generator
 * up to the consumer, mirroring the phase-boundary checks performed earlier
 * in the speech pipeline (before STT, between STT and LLM, between LLM and TTS).
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InterruptionStreamListener implements ListenerInterface
{
    public function __construct(
        private readonly InterruptionSignalInterface $signal,
    ) {
    }

    public function onStart(StartEvent $event): void
    {
        $this->abortIfInterrupted();
    }

    public function onDelta(DeltaEvent $event): void
    {
        $this->abortIfInterrupted();
    }

    public function onComplete(CompleteEvent $event): void
    {
    }

    private function abortIfInterrupted(): void
    {
        if ($this->signal->isInterrupted()) {
            throw new InterruptedException();
        }
    }
}
