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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\WorkflowGuardException;
use Symfony\AI\Agent\Exception\WorkflowMaxStepsExceededException;
use Symfony\AI\Agent\Workflow\Executor\RetryExecutor;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\RetryStrategy;
use Symfony\AI\Agent\Workflow\SleeperInterface;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Exception\RateLimitExceededException;

final class RetryExecutorTest extends TestCase
{
    public function testSuccessOnFirstAttemptNeverCallsSleeper()
    {
        $state = new WorkflowState('run-1', ['input' => 'hello']);
        $expectedState = $state->set('output', 'world');

        $inner = new FixedResultExecutor($expectedState);
        $sleeper = new ThrowingOnCallSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 3, baseDelayMs: 100, sleeper: $sleeper);

        $actual = $executor->execute($state, 'place_a');

        $this->assertSame('world', $actual->get('output'));
        $this->assertSame(1, $inner->callCount);
    }

    public function testRetriesOnTransientFailureThenSucceeds()
    {
        $state = new WorkflowState('run-2', ['input' => 'hello']);
        $successState = $state->set('output', 'ok');

        $inner = new FailNTimesExecutor(failCount: 2, successState: $successState);
        $sleeper = new RecordingDelaysSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 5, baseDelayMs: 100, strategy: RetryStrategy::Fixed, sleeper: $sleeper);

        $actual = $executor->execute($state, 'place_b');

        $this->assertSame('ok', $actual->get('output'));
        $this->assertCount(2, $sleeper->recordedDelays);
    }

    public function testExhaustsMaxAttemptsAndRethrowsLastException()
    {
        $state = new WorkflowState('run-3');
        $last = null;

        $inner = new AlwaysFailingExecutor();
        $sleeper = new NoOpSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 3, baseDelayMs: 50, sleeper: $sleeper);

        try {
            $executor->execute($state, 'place_c');
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $last = $e;
        }

        $this->assertNotNull($last);
        $this->assertSame('failure 3', $last->getMessage());
    }

    public function testWorkflowGuardExceptionIsNeverRetried()
    {
        $state = new WorkflowState('run-4');

        $inner = new class implements ExecutorInterface {
            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                throw new WorkflowGuardException('guard blocked');
            }
        };

        $sleeper = new ThrowingOnCallSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 5, baseDelayMs: 100, sleeper: $sleeper);

        $this->expectException(WorkflowGuardException::class);
        $this->expectExceptionMessage('guard blocked');

        $executor->execute($state, 'place_d');
    }

    public function testWorkflowMaxStepsExceededExceptionIsNeverRetried()
    {
        $state = new WorkflowState('run-5');

        $inner = new class implements ExecutorInterface {
            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                throw new WorkflowMaxStepsExceededException(10);
            }
        };

        $sleeper = new ThrowingOnCallSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 5, baseDelayMs: 100, sleeper: $sleeper);

        $this->expectException(WorkflowMaxStepsExceededException::class);

        $executor->execute($state, 'place_e');
    }

    public function testRateLimitExceededDirectlyHonorsRetryAfter()
    {
        $state = new WorkflowState('run-6');
        $successState = $state->set('output', 'done');

        // fail once with RateLimitExceededException(retryAfter=5), then succeed
        $inner = new RateLimitThenSucceedExecutor(retryAfter: 5, successState: $successState);
        $sleeper = new RecordingDelaysSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 3, baseDelayMs: 100, sleeper: $sleeper);
        $executor->execute($state, 'place_f');

        $this->assertCount(1, $sleeper->recordedDelays);
        $this->assertSame(5000, $sleeper->recordedDelays[0]);
    }

    public function testRateLimitExceededWrappedOneLevelDeepHonorsRetryAfter()
    {
        $state = new WorkflowState('run-7');
        $successState = $state->set('output', 'done');

        // fail once with a RuntimeException wrapping RateLimitExceededException(retryAfter=8), then succeed
        $inner = new WrappedRateLimitThenSucceedExecutor(retryAfter: 8, successState: $successState);
        $sleeper = new RecordingDelaysSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 3, baseDelayMs: 200, sleeper: $sleeper);
        $executor->execute($state, 'place_g');

        $this->assertCount(1, $sleeper->recordedDelays);
        $this->assertSame(8000, $sleeper->recordedDelays[0]);
    }

    public function testFixedStrategyProducesSameDelayEachAttempt()
    {
        $state = new WorkflowState('run-8');

        $inner = new FailNTimesExecutor(failCount: 3, successState: $state);
        $sleeper = new RecordingDelaysSleeper();

        $executor = new RetryExecutor($inner, maxAttempts: 5, baseDelayMs: 300, strategy: RetryStrategy::Fixed, sleeper: $sleeper);
        $executor->execute($state, 'place_h');

        $this->assertCount(3, $sleeper->recordedDelays);
        foreach ($sleeper->recordedDelays as $delay) {
            $this->assertSame(300, $delay);
        }
    }

    public function testExponentialStrategyDoublesDelayAfterEachAttempt()
    {
        $state = new WorkflowState('run-9');

        $inner = new FailNTimesExecutor(failCount: 3, successState: $state);
        $sleeper = new RecordingDelaysSleeper();

        // Exponential: attempt 1 → base<<0 = 100, attempt 2 → base<<1 = 200, attempt 3 → base<<2 = 400
        $executor = new RetryExecutor($inner, maxAttempts: 5, baseDelayMs: 100, strategy: RetryStrategy::Exponential, sleeper: $sleeper);
        $executor->execute($state, 'place_i');

        $this->assertCount(3, $sleeper->recordedDelays);
        $this->assertSame(100, $sleeper->recordedDelays[0]); // attempt 1: 100 << (1-1) = 100
        $this->assertSame(200, $sleeper->recordedDelays[1]); // attempt 2: 100 << (2-1) = 200
        $this->assertSame(400, $sleeper->recordedDelays[2]); // attempt 3: 100 << (3-1) = 400
    }
}

// ---------------------------------------------------------------------------
// Test doubles — defined at file scope to avoid anonymous-class PHPStan issues
// ---------------------------------------------------------------------------

/**
 * Always returns the given state on the first call, never fails.
 *
 * @internal
 */
final class FixedResultExecutor implements ExecutorInterface
{
    public int $callCount = 0;

    public function __construct(private readonly WorkflowStateInterface $result)
    {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        ++$this->callCount;

        return $this->result;
    }
}

/**
 * Throws a generic RuntimeException for the first $failCount calls, then returns $successState.
 *
 * @internal
 */
final class FailNTimesExecutor implements ExecutorInterface
{
    private int $callCount = 0;

    public function __construct(
        private readonly int $failCount,
        private readonly WorkflowStateInterface $successState,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        ++$this->callCount;
        if ($this->callCount <= $this->failCount) {
            throw new \RuntimeException('transient failure '.$this->callCount);
        }

        return $this->successState;
    }
}

/**
 * Always throws a RuntimeException with a message containing the attempt number.
 *
 * @internal
 */
final class AlwaysFailingExecutor implements ExecutorInterface
{
    private int $callCount = 0;

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        ++$this->callCount;
        throw new \RuntimeException('failure '.$this->callCount);
    }
}

/**
 * Throws a RateLimitExceededException on the first call, then succeeds.
 *
 * @internal
 */
final class RateLimitThenSucceedExecutor implements ExecutorInterface
{
    private int $callCount = 0;

    public function __construct(
        private readonly int $retryAfter,
        private readonly WorkflowStateInterface $successState,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        ++$this->callCount;
        if (1 === $this->callCount) {
            throw new RateLimitExceededException(retryAfter: $this->retryAfter);
        }

        return $this->successState;
    }
}

/**
 * Throws a RuntimeException wrapping a RateLimitExceededException on the first call, then succeeds.
 *
 * @internal
 */
final class WrappedRateLimitThenSucceedExecutor implements ExecutorInterface
{
    private int $callCount = 0;

    public function __construct(
        private readonly int $retryAfter,
        private readonly WorkflowStateInterface $successState,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        ++$this->callCount;
        if (1 === $this->callCount) {
            $cause = new RateLimitExceededException(retryAfter: $this->retryAfter);
            throw new \RuntimeException('wrapped rate limit', 0, $cause);
        }

        return $this->successState;
    }
}

/**
 * Records every millisecond value passed to sleep() for later assertions.
 *
 * @internal
 */
final class RecordingDelaysSleeper implements SleeperInterface
{
    /** @var list<int> */
    public array $recordedDelays = [];

    public function sleep(int $milliseconds): void
    {
        $this->recordedDelays[] = $milliseconds;
    }
}

/**
 * Throws a LogicException whenever sleep() is invoked (the test double must never sleep).
 *
 * @internal
 */
final class ThrowingOnCallSleeper implements SleeperInterface
{
    public function sleep(int $milliseconds): void
    {
        throw new \LogicException('Sleeper must not be called in this test.');
    }
}

/**
 * Silently ignores every sleep() call.
 *
 * @internal
 */
final class NoOpSleeper implements SleeperInterface
{
    public function sleep(int $milliseconds): void
    {
        // intentionally no-op
    }
}
