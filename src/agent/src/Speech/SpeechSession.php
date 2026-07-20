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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\CancellableInterface;
use Symfony\AI\Platform\Result\InterruptionSignal;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Keeps a handle on the last cancellable result produced by the wrapped agent
 * so that a new call() automatically supersedes the previous one.
 *
 * Typical usage: a long-running process (WebSocket handler, CLI daemon, event loop)
 * where a fresh user input must interrupt an in-flight pipeline before starting
 * a new call.
 *
 * In addition to cancelling the previous result, the session manages an
 * `InterruptionSignal` that is injected as an option into the wrapped agent.
 * A `SpeechAgent` (or any other cooperative implementation) consumes the signal
 * to abort between phases (e.g. between STT and LLM, between LLM and TTS) in
 * contexts where a new call() can be issued during the previous one's execution
 * (Fibers, event loops).
 *
 * Limitation: a non-cancellable result (e.g. a fully materialised TextResult) is not
 * retained — there is nothing to cancel next time. A synchronous HTTP call already
 * in flight cannot be aborted from the outside in synchronous PHP; the session is
 * only effective when the retained result is still holding in-flight I/O (streaming
 * TTS, unresolved DeferredResult) or when the wrapped agent checks the interruption
 * signal at phase boundaries.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechSession implements AgentInterface
{
    private ?CancellableInterface $inFlight = null;
    private ?InterruptionSignal $signal = null;

    public function __construct(
        private readonly AgentInterface $agent,
    ) {
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $this->supersede();

        $this->signal = new InterruptionSignal();
        $options['interruption_signal'] = $this->signal;

        $result = $this->agent->call($messages, $options);
        if ($result instanceof CancellableInterface) {
            $this->inFlight = $result;
        }

        return $result;
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * Flips the previous signal and cancels the previous in-flight result.
     */
    private function supersede(): void
    {
        $this->signal?->interrupt();
        $this->signal = null;

        if (null !== $this->inFlight) {
            $this->inFlight->cancel();
            $this->inFlight = null;
        }
    }
}
