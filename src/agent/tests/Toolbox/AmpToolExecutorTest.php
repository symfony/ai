<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\AmpToolExecutor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

use function Amp\async;
use function Amp\delay;

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class AmpToolExecutorTest extends TestCase
{
    public function testItOverlapsAmpWaitsAndPreservesResultAndProgressOrder()
    {
        $tool = new #[AsTool('cooperative_tool', 'Waits cooperatively for another tool call.')]
        class {
            /**
             * @var list<string>
             */
            public array $events = [];

            /**
             * @var DeferredFuture<null>
             */
            private readonly DeferredFuture $secondCompleted;

            public function __construct()
            {
                $this->secondCompleted = new DeferredFuture();
            }

            public function __invoke(string $name): string
            {
                $this->events[] = $name.':start';
                delay(0);

                if ('first' === $name) {
                    $this->secondCompleted->getFuture()->await(new TimeoutCancellation(5));
                }

                $this->events[] = $name.':end';

                if ('second' === $name) {
                    $this->secondCompleted->complete();
                }

                return $name;
            }
        };

        $firstCall = new ToolCall('first', 'cooperative_tool', ['name' => 'first']);
        $secondCall = new ToolCall('second', 'cooperative_tool', ['name' => 'second']);
        $executor = new AmpToolExecutor(new Toolbox([$tool]));

        [$updates, $results] = async(static function () use ($executor, $firstCall, $secondCall): array {
            $generator = $executor->execute([$firstCall, $secondCall]);
            $updates = iterator_to_array($generator);

            return [$updates, $generator->getReturn()];
        })->await();

        $this->assertSame(['first:start', 'second:start', 'second:end', 'first:end'], $tool->events);
        $this->assertCount(2, $results);
        $this->assertSame($firstCall, $results[0]->getToolCall());
        $this->assertSame('first', $results[0]->getResult());
        $this->assertSame($secondCall, $results[1]->getToolCall());
        $this->assertSame('second', $results[1]->getResult());
        $this->assertCount(2, $updates);
        $this->assertContainsOnlyInstancesOf(Progress::class, $updates);
        $this->assertSame(Progress::STAGE_TOOL_CALL, $updates[0]->getStage());
        $this->assertSame(Progress::STAGE_TOOL_CALL, $updates[1]->getStage());
        $this->assertSame($firstCall, $updates[0]->getPayload());
        $this->assertSame($secondCall, $updates[1]->getPayload());
    }

    public function testItSettlesSiblingCallsBeforePropagatingTheToolboxException()
    {
        $exception = new \RuntimeException('Tool exploded.');
        $events = [];
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('execute')->willReturnCallback(static function (ToolCall $toolCall) use ($exception, &$events): ToolResult {
            delay(0);

            if ('broken_tool' === $toolCall->getName()) {
                $events[] = 'failed';

                throw $exception;
            }

            delay(0);
            $events[] = 'settled';

            return new ToolResult($toolCall, 'Sibling completed.');
        });

        $executor = new AmpToolExecutor($toolbox);

        $this->expectExceptionObject($exception);

        try {
            iterator_to_array($executor->execute([
                new ToolCall('sibling', 'sibling_tool'),
                new ToolCall('broken', 'broken_tool'),
            ]));
        } finally {
            $this->assertSame(['failed', 'settled'], $events);
        }
    }
}
