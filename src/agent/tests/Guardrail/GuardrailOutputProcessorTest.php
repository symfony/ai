<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Guardrail;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\GuardrailException;
use Symfony\AI\Agent\Guardrail\GuardrailOutputProcessor;
use Symfony\AI\Agent\Guardrail\GuardrailResult;
use Symfony\AI\Agent\Guardrail\OutputGuardrailInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

final class GuardrailOutputProcessorTest extends TestCase
{
    public function testPassesWhenNoGuardrailsTriggered()
    {
        $guardrail = $this->createMock(OutputGuardrailInterface::class);
        $guardrail->method('validateOutput')->willReturn(GuardrailResult::pass());

        $processor = new GuardrailOutputProcessor([$guardrail]);
        $result = $this->createMock(ResultInterface::class);
        $output = new Output('gpt-4', $result, new MessageBag());

        $processor->processOutput($output);

        $this->addToAssertionCount(1);
    }

    public function testThrowsWhenGuardrailTriggered()
    {
        $blockedResult = GuardrailResult::block('output_scanner', 'Output blocked', 1.0);

        $guardrail = $this->createMock(OutputGuardrailInterface::class);
        $guardrail->method('validateOutput')->willReturn($blockedResult);

        $processor = new GuardrailOutputProcessor([$guardrail]);
        $result = $this->createMock(ResultInterface::class);
        $output = new Output('gpt-4', $result, new MessageBag());

        $this->expectException(GuardrailException::class);
        $this->expectExceptionMessage('Guardrail "output_scanner" triggered: Output blocked');

        $processor->processOutput($output);
    }

    public function testStopsAtFirstTriggeredGuardrail()
    {
        $passingGuardrail = $this->createMock(OutputGuardrailInterface::class);
        $passingGuardrail->method('validateOutput')->willReturn(GuardrailResult::pass());

        $blockingGuardrail = $this->createMock(OutputGuardrailInterface::class);
        $blockingGuardrail->method('validateOutput')
            ->willReturn(GuardrailResult::block('blocker', 'Blocked'));

        $neverCalledGuardrail = $this->createMock(OutputGuardrailInterface::class);
        $neverCalledGuardrail->expects($this->never())->method('validateOutput');

        $processor = new GuardrailOutputProcessor([
            $passingGuardrail,
            $blockingGuardrail,
            $neverCalledGuardrail,
        ]);

        $result = $this->createMock(ResultInterface::class);
        $output = new Output('gpt-4', $result, new MessageBag());

        $this->expectException(GuardrailException::class);

        $processor->processOutput($output);
    }

    public function testAcceptsTraversableGuardrails()
    {
        $guardrail = $this->createMock(OutputGuardrailInterface::class);
        $guardrail->method('validateOutput')->willReturn(GuardrailResult::pass());

        $processor = new GuardrailOutputProcessor(new \ArrayIterator([$guardrail]));
        $result = $this->createMock(ResultInterface::class);
        $output = new Output('gpt-4', $result, new MessageBag());

        $processor->processOutput($output);

        $this->addToAssertionCount(1);
    }
}
