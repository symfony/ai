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
use Symfony\AI\Agent\Guardrail\GuardrailInputProcessor;
use Symfony\AI\Agent\Guardrail\GuardrailResult;
use Symfony\AI\Agent\Guardrail\InputGuardrailInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class GuardrailInputProcessorTest extends TestCase
{
    public function testPassesWhenNoGuardrailsTriggered()
    {
        $guardrail = $this->createMock(InputGuardrailInterface::class);
        $guardrail->method('validateInput')->willReturn(GuardrailResult::pass());

        $processor = new GuardrailInputProcessor([$guardrail]);
        $input = new Input('gpt-4', new MessageBag(Message::ofUser('Hello')));

        $processor->processInput($input);

        $this->addToAssertionCount(1);
    }

    public function testThrowsWhenGuardrailTriggered()
    {
        $blockedResult = GuardrailResult::block('test_scanner', 'Blocked', 0.99);

        $guardrail = $this->createMock(InputGuardrailInterface::class);
        $guardrail->method('validateInput')->willReturn($blockedResult);

        $processor = new GuardrailInputProcessor([$guardrail]);
        $input = new Input('gpt-4', new MessageBag(Message::ofUser('Malicious input')));

        $this->expectException(GuardrailException::class);
        $this->expectExceptionMessage('Guardrail "test_scanner" triggered: Blocked (score: 0.99)');

        $processor->processInput($input);
    }

    public function testStopsAtFirstTriggeredGuardrail()
    {
        $passingGuardrail = $this->createMock(InputGuardrailInterface::class);
        $passingGuardrail->method('validateInput')->willReturn(GuardrailResult::pass());

        $blockingGuardrail = $this->createMock(InputGuardrailInterface::class);
        $blockingGuardrail->method('validateInput')
            ->willReturn(GuardrailResult::block('blocker', 'Blocked'));

        $neverCalledGuardrail = $this->createMock(InputGuardrailInterface::class);
        $neverCalledGuardrail->expects($this->never())->method('validateInput');

        $processor = new GuardrailInputProcessor([
            $passingGuardrail,
            $blockingGuardrail,
            $neverCalledGuardrail,
        ]);

        $input = new Input('gpt-4', new MessageBag(Message::ofUser('Test')));

        $this->expectException(GuardrailException::class);

        $processor->processInput($input);
    }

    public function testAcceptsTraversableGuardrails()
    {
        $guardrail = $this->createMock(InputGuardrailInterface::class);
        $guardrail->method('validateInput')->willReturn(GuardrailResult::pass());

        $processor = new GuardrailInputProcessor(new \ArrayIterator([$guardrail]));
        $input = new Input('gpt-4', new MessageBag(Message::ofUser('Hello')));

        $processor->processInput($input);

        $this->addToAssertionCount(1);
    }

    public function testGuardrailExceptionContainsResult()
    {
        $blockedResult = GuardrailResult::block('scanner_name', 'Reason text', 0.85);

        $guardrail = $this->createMock(InputGuardrailInterface::class);
        $guardrail->method('validateInput')->willReturn($blockedResult);

        $processor = new GuardrailInputProcessor([$guardrail]);
        $input = new Input('gpt-4', new MessageBag(Message::ofUser('Bad input')));

        try {
            $processor->processInput($input);
            $this->fail('Expected GuardrailException was not thrown.');
        } catch (GuardrailException $e) {
            $this->assertSame($blockedResult, $e->getGuardrailResult());
            $this->assertSame('scanner_name', $e->getGuardrailResult()->getScanner());
            $this->assertSame('Reason text', $e->getGuardrailResult()->getReason());
            $this->assertSame(0.85, $e->getGuardrailResult()->getScore());
        }
    }
}
