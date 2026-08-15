<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Job;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\JobTimeoutException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Tests\Fixtures\Job\ScriptedJobClient;
use Symfony\Component\Clock\MockClock;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobRunnerTest extends TestCase
{
    public function testItPollsUntilTheJobSucceeds()
    {
        $jobClient = $this->jobClient(
            new JobStatus(JobStateCase::QUEUED, 'Queueing'),
            new JobStatus(JobStateCase::RUNNING, 'Processing'),
            new JobStatus(JobStateCase::SUCCEEDED, 'Success'),
        );

        $clock = new MockClock('2026-01-01 00:00:00');
        $result = (new JobRunner($clock, 2.0))->wait($jobClient, new JobHandle('task-1'));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('done', $result->getContent());
        $this->assertSame(3, $jobClient->statusCalls);

        // Slept after the two non-terminal polls, not after the successful one.
        $this->assertSame('2026-01-01 00:00:04', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testItDoesNotSleepWhenTheJobIsAlreadyDone()
    {
        $jobClient = $this->jobClient(new JobStatus(JobStateCase::SUCCEEDED, 'Success'));

        $result = (new JobRunner(new MockClock()))->wait($jobClient, new JobHandle('task-1'));

        $this->assertSame('done', $result->getContent());
        $this->assertSame(1, $jobClient->statusCalls);
    }

    public function testItKeepsPollingOnAStateItDoesNotKnow()
    {
        $jobClient = $this->jobClient(
            new JobStatus(JobStateCase::UNKNOWN, 'SomethingNewTheProviderAdded'),
            new JobStatus(JobStateCase::SUCCEEDED, 'Success'),
        );

        $result = (new JobRunner(new MockClock()))->wait($jobClient, new JobHandle('task-1'));

        $this->assertSame('done', $result->getContent());
        $this->assertSame(2, $jobClient->statusCalls);
    }

    public function testItThrowsWithTheProvidersOwnWordingWhenTheJobFails()
    {
        $jobClient = $this->jobClient(new JobStatus(JobStateCase::FAILED, 'Fail', 'invalid prompt'));

        try {
            (new JobRunner(new MockClock()))->wait($jobClient, new JobHandle('task-1'));
            $this->fail(\sprintf('Expected a "%s".', JobFailedException::class));
        } catch (JobFailedException $exception) {
            $this->assertStringContainsString('ended as "Fail"', $exception->getMessage());
            $this->assertStringContainsString('invalid prompt', $exception->getMessage());
            $this->assertTrue($exception->getStatus()->is(JobStateCase::FAILED));
        }

        // A terminal failure must not be polled again.
        $this->assertSame(1, $jobClient->statusCalls);
    }

    #[DataProvider('provideTerminalFailureStates')]
    public function testItThrowsOnEveryTerminalStateWithoutAResult(JobStateCase $case)
    {
        $this->expectException(JobFailedException::class);
        $this->expectExceptionMessage($case->value);

        (new JobRunner(new MockClock()))->wait(
            $this->jobClient(new JobStatus($case, $case->value)),
            new JobHandle('task-1'),
        );
    }

    /**
     * @return iterable<string, array{JobStateCase}>
     */
    public static function provideTerminalFailureStates(): iterable
    {
        yield 'failed' => [JobStateCase::FAILED];
        yield 'expired' => [JobStateCase::EXPIRED];
        yield 'canceled' => [JobStateCase::CANCELED];
    }

    /**
     * The bridge knows that video generation runs for minutes where speech takes seconds; the caller
     * usually does not. So a handle that states how long it may take decides the budget.
     */
    public function testItWaitsAsLongAsTheJobSaysItNeeds()
    {
        $jobClient = $this->jobClient(...array_fill(0, 200, new JobStatus(JobStateCase::RUNNING, 'Processing')));

        try {
            (new JobRunner(new MockClock(), 2.0))->wait($jobClient, new JobHandle('task-1', maxDuration: 300));
            $this->fail(\sprintf('Expected a "%s".', JobTimeoutException::class));
        } catch (JobTimeoutException) {
        }

        // 300 seconds at one poll every two seconds.
        $this->assertSame(150, $jobClient->statusCalls);
    }

    public function testARunnerBudgetOverrulesWhatTheJobAsksFor()
    {
        $jobClient = $this->jobClient(...array_fill(0, 10, new JobStatus(JobStateCase::RUNNING, 'Processing')));

        try {
            (new JobRunner(new MockClock(), 1.0, 3))->wait($jobClient, new JobHandle('task-1', maxDuration: 600));
            $this->fail(\sprintf('Expected a "%s".', JobTimeoutException::class));
        } catch (JobTimeoutException) {
        }

        $this->assertSame(3, $jobClient->statusCalls);
    }

    /**
     * How long to wait usually belongs to the call, not to the runner: the same job may be given ten
     * minutes in a worker and five seconds inside a web request, both through the same shared
     * service. So a per-call budget wins over everything else.
     */
    public function testACallBudgetOverrulesTheRunnerAndTheJob()
    {
        $jobClient = $this->jobClient(...array_fill(0, 10, new JobStatus(JobStateCase::RUNNING, 'Processing')));
        $runner = new JobRunner(new MockClock(), 1.0, 300);

        try {
            $runner->wait($jobClient, new JobHandle('task-1', maxDuration: 600), maxDuration: 4);
            $this->fail(\sprintf('Expected a "%s".', JobTimeoutException::class));
        } catch (JobTimeoutException $exception) {
            $this->assertStringContainsString('did not finish within 4 second(s)', $exception->getMessage());
        }

        $this->assertSame(4, $jobClient->statusCalls);
    }

    public function testTheCallBudgetIsSpentInSecondsRegardlessOfThePollInterval()
    {
        $jobClient = $this->jobClient(...array_fill(0, 10, new JobStatus(JobStateCase::RUNNING, 'Processing')));

        try {
            (new JobRunner(new MockClock(), 2.0))->wait($jobClient, new JobHandle('task-1'), maxDuration: 10);
            $this->fail(\sprintf('Expected a "%s".', JobTimeoutException::class));
        } catch (JobTimeoutException) {
        }

        // 10 seconds at one poll every two seconds - the caller states time, not poll counts.
        $this->assertSame(5, $jobClient->statusCalls);
    }

    public function testItRejectsANonsensicalCallBudget()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one second');

        (new JobRunner(new MockClock()))->wait($this->jobClient(), new JobHandle('task-1'), maxDuration: 0);
    }

    public function testAHandleWithoutAnExpectationFallsBackToTheDefaultBudget()
    {
        $jobClient = $this->jobClient(...array_fill(0, 200, new JobStatus(JobStateCase::RUNNING, 'Processing')));

        try {
            (new JobRunner(new MockClock()))->wait($jobClient, new JobHandle('task-1'));
            $this->fail(\sprintf('Expected a "%s".', JobTimeoutException::class));
        } catch (JobTimeoutException) {
        }

        $this->assertSame(120, $jobClient->statusCalls);
    }

    public function testItGivesUpAfterTheLastPollAndHandsTheHandleBack()
    {
        $jobClient = $this->jobClient(...array_fill(0, 3, new JobStatus(JobStateCase::RUNNING, 'Processing')));
        $handle = new JobHandle('task-1');

        try {
            (new JobRunner(new MockClock(), 1.0, 3))->wait($jobClient, $handle);
            $this->fail(\sprintf('Expected a "%s".', JobTimeoutException::class));
        } catch (JobTimeoutException $exception) {
            $this->assertStringContainsString('did not finish within 3 second(s)', $exception->getMessage());
            $this->assertSame($handle, $exception->getHandle());
        }

        $this->assertSame(3, $jobClient->statusCalls);
        $this->assertSame(0, $jobClient->resultCalls);
    }

    public function testItRejectsANonsensicalPollInterval()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        new JobRunner(new MockClock(), 0.0);
    }

    public function testItRejectsANonsensicalRunnerBudget()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one second');

        new JobRunner(new MockClock(), 1.0, 0);
    }

    private function jobClient(JobStatus ...$statuses): ScriptedJobClient
    {
        return new ScriptedJobClient(...$statuses);
    }
}
