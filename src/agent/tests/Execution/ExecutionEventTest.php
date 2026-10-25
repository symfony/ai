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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\ExecutionEvent;
use Symfony\AI\Agent\Execution\Update\Progress;

final class ExecutionEventTest extends TestCase
{
    public function testItExposesItsValues()
    {
        $payload = new \stdClass();
        $event = new ExecutionEvent(2, 'tool_call', 'Calling tool.', $payload);

        $this->assertSame(2, $event->getSequence());
        $this->assertSame('tool_call', $event->getStage());
        $this->assertSame('Calling tool.', $event->getMessage());
        $this->assertSame($payload, $event->getPayload());
    }

    public function testMessageAndPayloadAreOptional()
    {
        $event = new ExecutionEvent(1, 'model_request');

        $this->assertSame('', $event->getMessage());
        $this->assertNull($event->getPayload());
    }

    public function testFromProgress()
    {
        $event = ExecutionEvent::fromProgress(7, new Progress('model_request', 'Invoking model.', 'gpt-4'));

        $this->assertSame(7, $event->getSequence());
        $this->assertSame('model_request', $event->getStage());
        $this->assertSame('Invoking model.', $event->getMessage());
        $this->assertSame('gpt-4', $event->getPayload());
    }
}
