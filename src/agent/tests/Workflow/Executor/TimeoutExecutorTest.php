<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Executor;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\AI\Agent\Exception\WorkflowTimeoutException;
use Symfony\AI\Agent\Workflow\Executor\TimeoutExecutor;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TimeoutExecutorTest extends TestCase
{
    public function testZeroTimeoutRunsInnerExecutorWithoutTimeout()
    {
        $state = new WorkflowState('run-1', ['key' => 'value']);
        $expected = $state->set('result', 'done');

        $inner = new FixedStateExecutor($expected);
        $executor = new TimeoutExecutor($inner, 0);

        $actual = $executor->execute($state, 'place_a');

        $this->assertSame('done', $actual->get('result'));
        $this->assertSame(1, $inner->callCount);
    }

    public function testNegativeTimeoutRunsInnerExecutorWithoutTimeout()
    {
        $state = new WorkflowState('run-2', ['key' => 'value']);
        $expected = $state->set('result', 'ok');

        $inner = new FixedStateExecutor($expected);
        $executor = new TimeoutExecutor($inner, -5);

        $actual = $executor->execute($state, 'place_b');

        $this->assertSame('ok', $actual->get('result'));
        $this->assertSame(1, $inner->callCount);
    }

    public function testZeroTimeoutReturnsInnerStateUnchanged()
    {
        $state = new WorkflowState('run-3', ['answer' => 42]);
        $inner = new FixedStateExecutor($state);
        $executor = new TimeoutExecutor($inner, 0);

        $actual = $executor->execute($state, 'place_c');

        $this->assertSame($state, $actual);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testExecutorFinishingBeforeTimeoutReturnsStateNormally()
    {
        $state = new WorkflowState('run-4', ['input' => 'hello']);
        $expected = $state->set('output', 'world');

        $inner = new FixedStateExecutor($expected);
        // 10-second timeout; the inner executor is instant
        $executor = new TimeoutExecutor($inner, 10);

        $actual = $executor->execute($state, 'place_d');

        $this->assertSame('world', $actual->get('output'));
        $this->assertSame(1, $inner->callCount);
    }

    /**
     * Verify the WorkflowTimeoutException accessors independently of the executor machinery.
     * The SIGALRM-based throw cannot be statically traced by PHPStan, so we test the exception
     * object directly here.
     */
    public function testWorkflowTimeoutExceptionExposesMeaningfulAccessors()
    {
        $exception = new WorkflowTimeoutException('my_place', 42);

        $this->assertSame('my_place', $exception->getPlace());
        $this->assertSame(42, $exception->getTimeout());
        $this->assertStringContainsString('my_place', $exception->getMessage());
        $this->assertStringContainsString('42', $exception->getMessage());
    }

    public function testWorkflowTimeoutExceptionForwardsPreviousThrowable()
    {
        $cause = new \LogicException('root cause');
        $exception = new WorkflowTimeoutException('place_x', 5, $cause);

        $this->assertSame($cause, $exception->getPrevious());
    }

    #[RequiresPhpExtension('pcntl')]
    public function testTimeoutThrowsWorkflowTimeoutExceptionWhenInnerIsSlow()
    {
        $state = new WorkflowState('run-5');
        $inner = new SleepingExecutor(5);
        $executor = new TimeoutExecutor($inner, 1);

        $this->expectException(WorkflowTimeoutException::class);
        $this->expectExceptionMessage('step_slow');

        $executor->execute($state, 'step_slow');
    }

    #[RequiresPhpExtension('pcntl')]
    public function testTimeoutExceptionBubblesWhenInnerExecutorRewrapsIt()
    {
        $state = new WorkflowState('run-6');
        $inner = new RewrappingTimeoutExecutor(5);
        $executor = new TimeoutExecutor($inner, 1);

        $this->expectException(WorkflowTimeoutException::class);

        $executor->execute($state, 'step_wrap');
    }

    #[RequiresPhpExtension('pcntl')]
    public function testNonTimeoutExceptionFromInnerIsPropagatedUnchanged()
    {
        $state = new WorkflowState('run-7');
        $inner = new ThrowingExecutor(new \LogicException('something went wrong'));
        $executor = new TimeoutExecutor($inner, 10);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('something went wrong');

        $executor->execute($state, 'place_logic');
    }

    public function testPcntlUnavailableLogsWarningAndRunsWithoutTimeout()
    {
        if (\function_exists('pcntl_alarm')) {
            $this->markTestSkipped('pcntl is available on this system; this test requires it to be absent.');
        }

        $state = new WorkflowState('run-8', ['x' => 1]);
        $expected = $state->set('x', 2);

        $inner = new FixedStateExecutor($expected);
        $logger = new CapturingLogger();

        $executor = new TimeoutExecutor($inner, 5, $logger);

        $actual = $executor->execute($state, 'place_noPcntl');

        $this->assertSame(2, $actual->get('x'));
        $this->assertSame(1, $inner->callCount);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertStringContainsString('place_noPcntl', $logger->records[0]['message']);
    }
}

// ---------------------------------------------------------------------------
// Test doubles — defined at file scope to avoid anonymous-class PHPStan issues
// ---------------------------------------------------------------------------

/**
 * Always returns the state it was constructed with.
 *
 * @internal
 */
final class FixedStateExecutor implements ExecutorInterface
{
    public int $callCount = 0;

    public function __construct(private readonly WorkflowStateInterface $state)
    {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        ++$this->callCount;

        return $this->state;
    }
}

/**
 * Sleeps for a given number of seconds; used to trigger SIGALRM-based timeouts.
 *
 * @internal
 */
final class SleepingExecutor implements ExecutorInterface
{
    public function __construct(private readonly int $seconds)
    {
    }

    /**
     * @throws WorkflowTimeoutException When the surrounding TimeoutExecutor fires SIGALRM
     */
    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        sleep($this->seconds);

        return $state;
    }
}

/**
 * Sleeps and re-wraps any thrown exception in a RuntimeException; used to verify
 * that the TimeoutExecutor surfaces the underlying WorkflowTimeoutException.
 *
 * @internal
 */
final class RewrappingTimeoutExecutor implements ExecutorInterface
{
    public function __construct(private readonly int $seconds)
    {
    }

    /**
     * @throws \RuntimeException        Always wraps any exception coming from sleep()
     * @throws WorkflowTimeoutException Annotated so PHPStan sees the outer catch as reachable
     */
    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        try {
            sleep($this->seconds);
        } catch (\Throwable $e) {
            throw new \RuntimeException('wrapped: '.$e->getMessage(), 0, $e);
        }

        return $state;
    }
}

/**
 * Always throws the given exception, regardless of input.
 *
 * @internal
 */
final class ThrowingExecutor implements ExecutorInterface
{
    public function __construct(private readonly \Throwable $exception)
    {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        throw $this->exception;
    }
}

/**
 * Captures every PSR-3 log record for later assertions.
 *
 * @internal
 */
final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
