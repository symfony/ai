<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Execution\AbstractExecution;
use Symfony\AI\Agent\Execution\ExecutionStatus;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

final class AbstractExecutionTest extends TestCase
{
    #[DataProvider('provideStatuses')]
    public function testIsSuspendedOnlyWhileWaitingForInput(ExecutionStatus $status, bool $expected)
    {
        $this->assertSame($expected, $this->createExecution($status)->isSuspended());
    }

    /**
     * @return iterable<string, array{ExecutionStatus, bool}>
     */
    public static function provideStatuses(): iterable
    {
        foreach (ExecutionStatus::cases() as $status) {
            yield $status->name => [$status, ExecutionStatus::WaitingForInput === $status];
        }
    }

    public function testErrorCodeAndEventsAreEmptyByDefault()
    {
        $execution = $this->createExecution();

        $this->assertNull($execution->getErrorCode());
        $this->assertSame([], $execution->getEventsSince());
        $this->assertSame([], $execution->getEventsSince(5));
    }

    public function testResultAccessorsDelegateToTheResult()
    {
        $result = new TextResult('Hello');
        $rawResult = new InMemoryRawResult(['id' => 'raw']);

        $execution = $this->createExecution(result: $result);

        $this->assertSame('Hello', $execution->getContent());
        $this->assertSame($result->getMetadata(), $execution->getMetadata());
        $this->assertNull($execution->getRawResult());

        $execution->setRawResult($rawResult);

        $this->assertSame($rawResult, $result->getRawResult());
        $this->assertSame($rawResult, $execution->getRawResult());
    }

    public function testTypedAccessorsReadTheResult()
    {
        $this->assertSame('Hello', $this->createExecution()->asText());
    }

    public function testIterationYieldsNothing()
    {
        $this->assertSame([], iterator_to_array($this->createExecution()));
    }

    public function testCallbacksAreNotRegisteredAndReturnTheSameInstance()
    {
        $execution = $this->createExecution();
        $callback = static function (): void {
            throw new \LogicException('The callback must not be called.');
        };

        $this->assertSame($execution, $execution->onProgress($callback));
        $this->assertSame($execution, $execution->onResult($callback));
    }

    public function testItIsNotStreamed()
    {
        $execution = $this->createExecution();

        $this->assertFalse($execution->isStreamed());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The execution is not streamed.');

        $execution->asStream()->current();
    }

    public function testItCannotBeCancelled()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('This execution cannot be cancelled.');

        $this->createExecution()->cancel();
    }

    private function createExecution(ExecutionStatus $status = ExecutionStatus::Completed, ?ResultInterface $result = null): AbstractExecution
    {
        return new class($status, $result ?? new TextResult('Hello')) extends AbstractExecution {
            private readonly AbstractUid&TimeBasedUidInterface $id;

            public function __construct(
                private readonly ExecutionStatus $status,
                private readonly ResultInterface $result,
            ) {
                $this->id = Uuid::v7();
            }

            public function getId(): AbstractUid&TimeBasedUidInterface
            {
                return $this->id;
            }

            public function getStatus(): ExecutionStatus
            {
                return $this->status;
            }

            public function getPendingToolCalls(): array
            {
                return [];
            }

            public function getError(): ?string
            {
                return null;
            }

            public function getMessages(): MessageBag
            {
                return new MessageBag(Message::ofUser('Hi'));
            }

            public function getResult(): ResultInterface
            {
                return $this->result;
            }
        };
    }
}
