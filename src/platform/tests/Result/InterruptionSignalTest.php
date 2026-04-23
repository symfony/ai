<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\InterruptionSignal;
use Symfony\AI\Platform\Result\InterruptionSignalInterface;

final class InterruptionSignalTest extends TestCase
{
    public function testDefaultStateIsNotInterrupted()
    {
        $signal = new InterruptionSignal();

        $this->assertFalse($signal->isInterrupted());
    }

    public function testInterruptFlipsState()
    {
        $signal = new InterruptionSignal();
        $signal->interrupt();

        $this->assertTrue($signal->isInterrupted());
    }

    public function testInterruptIsIdempotent()
    {
        $signal = new InterruptionSignal();

        $signal->interrupt();
        $signal->interrupt();
        $signal->interrupt();

        $this->assertTrue($signal->isInterrupted());
    }

    public function testImplementsInterface()
    {
        $signal = new InterruptionSignal();

        $this->assertInstanceOf(InterruptionSignalInterface::class, $signal);
    }
}
