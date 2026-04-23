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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Speech\SpeechSession;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\InterruptionSignal;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

final class SpeechSessionTest extends TestCase
{
    public function testCallDelegatesToInnerAgent()
    {
        $expectedResult = new TextResult('hello');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($expectedResult);

        $session = new SpeechSession($innerAgent);
        $result = $session->call(new MessageBag(Message::ofUser('Hi')));

        $this->assertSame($expectedResult, $result);
    }

    public function testCallCancelsPreviousCancellableResult()
    {
        $firstStream = new StreamResult((static function () {
            yield new TextDelta('one');
        })());
        $secondStream = new StreamResult((static function () {
            yield new TextDelta('two');
        })());

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->exactly(2))
            ->method('call')
            ->willReturnOnConsecutiveCalls($firstStream, $secondStream);

        $session = new SpeechSession($innerAgent);

        $returnedFirst = $session->call(new MessageBag(Message::ofUser('first')));
        $this->assertSame($firstStream, $returnedFirst);
        $this->assertFalse($firstStream->isCancelled());

        $returnedSecond = $session->call(new MessageBag(Message::ofUser('second')));
        $this->assertSame($secondStream, $returnedSecond);
        $this->assertTrue($firstStream->isCancelled());
        $this->assertFalse($secondStream->isCancelled());
    }

    public function testCallIgnoresNonCancellableResult()
    {
        $firstResult = new TextResult('one');
        $secondResult = new TextResult('two');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->exactly(2))
            ->method('call')
            ->willReturnOnConsecutiveCalls($firstResult, $secondResult);

        $session = new SpeechSession($innerAgent);

        $returnedFirst = $session->call(new MessageBag(Message::ofUser('first')));
        $returnedSecond = $session->call(new MessageBag(Message::ofUser('second')));

        $this->assertSame($firstResult, $returnedFirst);
        $this->assertSame($secondResult, $returnedSecond);
    }

    public function testCallReleasesPreviousReferenceOnceCancelled()
    {
        $firstStream = new StreamResult((static function () {
            yield new TextDelta('one');
        })());
        $secondStream = new StreamResult((static function () {
            yield new TextDelta('two');
        })());

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->exactly(2))
            ->method('call')
            ->willReturnOnConsecutiveCalls($firstStream, $secondStream);

        $session = new SpeechSession($innerAgent);
        $session->call(new MessageBag(Message::ofUser('first')));
        $session->call(new MessageBag(Message::ofUser('second')));

        // The first one was cancelled during the second call.
        $this->assertTrue($firstStream->isCancelled());

        // Cancelling the second one directly still works and does not throw.
        $secondStream->cancel();
        $this->assertTrue($secondStream->isCancelled());
    }

    public function testCallInjectsInterruptionSignalInOptions()
    {
        $capturedSignal = null;

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages, array $options) use (&$capturedSignal) {
                $capturedSignal = $options['interruption_signal'] ?? null;

                return new TextResult('hi');
            });

        $session = new SpeechSession($innerAgent);
        $session->call(new MessageBag(Message::ofUser('hi')));

        $this->assertInstanceOf(InterruptionSignal::class, $capturedSignal);
        $this->assertFalse($capturedSignal->isInterrupted());
    }

    public function testCallFiresPreviousSignal()
    {
        $signals = [];

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->exactly(2))
            ->method('call')
            ->willReturnCallback(static function (MessageBag $messages, array $options) use (&$signals) {
                $signals[] = $options['interruption_signal'];

                return new TextResult('response');
            });

        $session = new SpeechSession($innerAgent);
        $session->call(new MessageBag(Message::ofUser('first')));
        $session->call(new MessageBag(Message::ofUser('second')));

        $this->assertCount(2, $signals);
        $this->assertTrue($signals[0]->isInterrupted(), 'The first signal must be fired by the second call()');
        $this->assertFalse($signals[1]->isInterrupted(), 'The second signal must remain clear');
    }

    public function testGetNameDelegatesToInnerAgent()
    {
        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())->method('getName')->willReturn('inner-name');

        $session = new SpeechSession($innerAgent);

        $this->assertSame('inner-name', $session->getName());
    }
}
