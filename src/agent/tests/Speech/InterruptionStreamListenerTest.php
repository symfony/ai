<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Speech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Speech\InterruptionStreamListener;
use Symfony\AI\Platform\Result\Exception\InterruptedException;
use Symfony\AI\Platform\Result\InterruptionSignal;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\AI\Platform\Result\Stream\StartEvent;
use Symfony\AI\Platform\Result\StreamResult;

final class InterruptionStreamListenerTest extends TestCase
{
    public function testOnStartDoesNotThrowWhenSignalIsNotFired()
    {
        $signal = new InterruptionSignal();
        $listener = new InterruptionStreamListener($signal);

        $listener->onStart(new StartEvent($this->createStreamResult()));

        $this->assertFalse($signal->isInterrupted());
    }

    public function testOnStartThrowsWhenSignalIsFired()
    {
        $signal = new InterruptionSignal();
        $signal->interrupt();
        $listener = new InterruptionStreamListener($signal);

        $this->expectException(InterruptedException::class);
        $listener->onStart(new StartEvent($this->createStreamResult()));
    }

    public function testOnDeltaDoesNotThrowWhenSignalIsNotFired()
    {
        $signal = new InterruptionSignal();
        $listener = new InterruptionStreamListener($signal);

        $listener->onDelta(new DeltaEvent($this->createStreamResult(), new TextDelta('hello')));

        $this->assertFalse($signal->isInterrupted());
    }

    public function testOnDeltaThrowsWhenSignalIsFired()
    {
        $signal = new InterruptionSignal();
        $signal->interrupt();
        $listener = new InterruptionStreamListener($signal);

        $this->expectException(InterruptedException::class);
        $listener->onDelta(new DeltaEvent($this->createStreamResult(), new TextDelta('hello')));
    }

    public function testOnCompleteIsNoop()
    {
        $signal = new InterruptionSignal();
        $signal->interrupt();
        $listener = new InterruptionStreamListener($signal);

        $listener->onComplete(new CompleteEvent($this->createStreamResult()));

        // Reaching this line proves no exception was thrown even with a fired signal.
        $this->assertTrue($signal->isInterrupted());
    }

    private function createStreamResult(): StreamResult
    {
        return new StreamResult((static function () {
            yield new TextDelta('placeholder');
        })());
    }
}
