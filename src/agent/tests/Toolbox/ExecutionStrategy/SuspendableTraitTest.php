<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\ExecutionStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ExecutionStrategy\SuspendableTrait;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class SuspendableTraitTest extends TestCase
{
    public function testSuspendIsNoOpOutsideFiber()
    {
        $tool = new class {
            use SuspendableTrait;

            public function run(): string
            {
                $this->suspend();

                return 'done';
            }
        };

        $this->assertSame('done', $tool->run());
    }

    public function testSuspendYieldsInsideFiber()
    {
        $log = [];

        $tool = new class {
            use SuspendableTrait;

            /**
             * @param array<int, string> $log
             */
            public function run(array &$log): string
            {
                $log[] = 'before_suspend';
                $this->suspend();
                $log[] = 'after_suspend';

                return 'done';
            }
        };

        $fiber = new \Fiber(static function () use ($tool, &$log): string {
            return $tool->run($log);
        });

        $fiber->start();

        // After start(), the fiber has suspended at $this->suspend(), so only 'before_suspend' is logged.
        $this->assertCount(1, $log);

        $fiber->resume();

        $this->assertTrue($fiber->isTerminated());
        $this->assertSame(['before_suspend', 'after_suspend'], $log);
        $this->assertSame('done', $fiber->getReturn());
    }

    public function testSuspendCanBeCalledMultipleTimes()
    {
        $tool = new class {
            use SuspendableTrait;

            public function run(): string
            {
                $this->suspend();
                $this->suspend();
                $this->suspend();

                return 'done';
            }
        };

        $resumeCount = 0;
        $fiber = new \Fiber(static function () use ($tool): string {
            return $tool->run();
        });

        $fiber->start();

        while (!$fiber->isTerminated()) {
            ++$resumeCount;
            $fiber->resume();
        }

        $this->assertSame(3, $resumeCount);
        $this->assertSame('done', $fiber->getReturn());
    }
}
