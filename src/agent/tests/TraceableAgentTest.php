<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\TraceableAgent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Stopwatch\Stopwatch;

final class TraceableAgentTest extends TestCase
{
    public function testDataAreCollectedWhenCallingAgent()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableAgent = new TraceableAgent(new MockAgent([
            'Hello there' => 'General Kenobi',
        ]), $clock);

        $messageBag = new MessageBag(
            Message::ofUser('Hello there'),
        );

        $traceableAgent->call($messageBag);

        $this->assertCount(1, $traceableAgent->getCalls());
        $this->assertEquals([
            [
                'input' => $messageBag,
                'context' => new Context(),
                'options' => [],
                'called_at' => $clock->now(),
            ],
        ], $traceableAgent->getCalls());
    }

    public function testExecutionIsReturnedAsIsWithoutStopwatch()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Result(new TextResult('General Kenobi'));
        });

        $traceableAgent = new TraceableAgent($this->createAgent($execution));

        $this->assertSame($execution, $traceableAgent->call('Hello there'));
    }

    public function testStopwatchEventSpansTheConsumptionOfTheExecution()
    {
        $stopwatch = new Stopwatch();
        $traceableAgent = new TraceableAgent(new MockAgent(['Hello there' => 'General Kenobi']), stopwatch: $stopwatch);

        $execution = $traceableAgent->call('Hello there');

        $this->assertFalse($stopwatch->isStarted('ai.agent.call "mock"'));

        $this->assertSame('General Kenobi', $execution->getResult()->getContent());

        $event = $stopwatch->getEvent('ai.agent.call "mock"');
        $this->assertSame('ai', $event->getCategory());
        $this->assertFalse($event->isStarted());
        $this->assertCount(1, $event->getPeriods());
    }

    public function testStopwatchEventStopsOnFailure()
    {
        $stopwatch = new Stopwatch();
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model', 'Calling the model.');

            throw new RuntimeException('Agent failed');
        });
        $traceableAgent = new TraceableAgent($this->createAgent($execution), stopwatch: $stopwatch);

        try {
            $traceableAgent->call('Hello there')->getResult();
            $this->fail('Expected the execution to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Agent failed', $exception->getMessage());
        }

        $event = $stopwatch->getEvent('ai.agent.call "my_agent"');
        $this->assertFalse($event->isStarted());
        $this->assertCount(1, $event->getPeriods());
    }

    public function testStreamedExecutionStaysStreamed()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Result(new TextResult('General Kenobi'));
        }, true);
        $traceableAgent = new TraceableAgent($this->createAgent($execution), stopwatch: new Stopwatch());

        $this->assertTrue($traceableAgent->call('Hello there')->isStreamed());
    }

    public function testCancellationIsForwardedToTheExecution()
    {
        $stopwatch = new Stopwatch();
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model', 'Calling the model.');
            yield new Result(new TextResult('General Kenobi'));
        });
        $traceableAgent = new TraceableAgent($this->createAgent($execution), stopwatch: $stopwatch);

        $traceableExecution = $traceableAgent->call('Hello there');
        foreach ($traceableExecution as $update) {
            $traceableExecution->cancel();
        }

        $this->assertFalse($stopwatch->getEvent('ai.agent.call "my_agent"')->isStarted());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $execution->getResult();
    }

    public function testCancellationBeforeConsumptionDoesNotRunTheAgent()
    {
        $stopwatch = new Stopwatch();
        $ran = false;
        $execution = new Execution(static function () use (&$ran): \Generator {
            $ran = true;
            yield new Result(new TextResult('General Kenobi'));
        });
        $traceableAgent = new TraceableAgent($this->createAgent($execution), stopwatch: $stopwatch);

        $traceableExecution = $traceableAgent->call('Hello there');
        $traceableExecution->cancel();

        try {
            $traceableExecution->getResult();
            $this->fail('Expected the execution to be canceled.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The agent execution was canceled.', $exception->getMessage());
        }

        $this->assertFalse($ran);
        $this->assertArrayNotHasKey('ai.agent.call "my_agent"', $stopwatch->getSectionEvents('__root__'));
    }

    public function testCancellationFromProgressCallbackStopsStopwatchEvent()
    {
        $stopwatch = new Stopwatch();
        $finished = false;
        $execution = new Execution(static function () use (&$finished): \Generator {
            yield new Progress('model', 'Calling the model.');
            yield new Progress('tool', 'Calling a tool.');
            $finished = true;
            yield new Result(new TextResult('General Kenobi'));
        });
        $traceableAgent = new TraceableAgent($this->createAgent($execution), stopwatch: $stopwatch);

        $traceableExecution = $traceableAgent->call('Hello there');
        $traceableExecution->onProgress(static function () use ($traceableExecution): void {
            $traceableExecution->cancel();
        });

        try {
            $traceableExecution->getResult();
            $this->fail('Expected the execution to be canceled.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The agent execution was canceled.', $exception->getMessage());
        }

        $this->assertFalse($finished);

        $event = $stopwatch->getEvent('ai.agent.call "my_agent"');
        $this->assertFalse($event->isStarted());
        $this->assertCount(1, $event->getPeriods());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $execution->getResult();
    }

    private function createAgent(Execution $execution): AgentInterface
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn($execution);
        $agent->method('getName')->willReturn('my_agent');

        return $agent;
    }
}
