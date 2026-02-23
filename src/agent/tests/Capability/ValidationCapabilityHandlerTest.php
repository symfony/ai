<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Capability\InputCapabilityInterface;
use Symfony\AI\Agent\Capability\InputDelayCapability;
use Symfony\AI\Agent\Capability\OutputDelayCapability;
use Symfony\AI\Agent\Capability\ValidationCapabilityHandler;
use Symfony\AI\Agent\Capability\ValidationGroupInputCapability;
use Symfony\AI\Agent\Capability\ValidationGroupOutputCapability;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\ValidationFailedException;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationCapabilityHandlerTest extends TestCase
{
    public function testHandlerSupport()
    {
        $validator = $this->createMock(ValidatorInterface::class);

        $handler = new ValidationCapabilityHandler($validator);

        $this->assertFalse($handler->support(new class implements InputCapabilityInterface {}));
        $this->assertFalse($handler->support(new InputDelayCapability(1)));
        $this->assertFalse($handler->support(new OutputDelayCapability(1)));
        $this->assertTrue($handler->support(new ValidationGroupInputCapability(['foo'])));
        $this->assertTrue($handler->support(new ValidationGroupOutputCapability(['bar'])));
    }

    public function testHandlerCannotHandleMessageBagWithoutUserMessage()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $handler = new ValidationCapabilityHandler($validator);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" capability handler requires either a user message or an assistant message.', ValidationCapabilityHandler::class));
        $this->expectExceptionCode(0);
        $handler->handle(new MockAgent(), new MessageBag(), [], new ValidationGroupInputCapability(['foo']));
    }

    public function testHandlerCanHandleMessageBagWithUserMessageAndWithoutViolations()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturn(new ConstraintViolationList());

        $handler = new ValidationCapabilityHandler($validator);
        $handler->handle(new MockAgent(), new MessageBag(Message::ofUser('Hello there')), [], new ValidationGroupInputCapability(['foo']));
    }

    public function testHandlerCanHandleMessageBagWithUserMessageAndViolations()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturn(new ConstraintViolationList([
            new ConstraintViolation('foo', 'bar', [], '', '', '', 0, null),
        ]));

        $handler = new ValidationCapabilityHandler($validator);

        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionCode(0);
        $handler->handle(new MockAgent(), new MessageBag(Message::ofUser('Hello there')), [], new ValidationGroupInputCapability(['foo']));
    }

    public function testHandlerCannotHandleMessageBagWithoutAssistantMessage()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $handler = new ValidationCapabilityHandler($validator);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" capability handler requires either a user message or an assistant message.', ValidationCapabilityHandler::class));
        $this->expectExceptionCode(0);
        $handler->handle(new MockAgent(), new MessageBag(), [], new ValidationGroupOutputCapability(['bar']));
    }

    public function testHandlerCanHandleMessageBagWithAssistantMessageMessageAndWithoutViolations()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturn(new ConstraintViolationList());

        $handler = new ValidationCapabilityHandler($validator);
        $handler->handle(new MockAgent(), new MessageBag(Message::ofAssistant('Hello there')), [], new ValidationGroupOutputCapability(['foo']));
    }

    public function testHandlerCanHandleMessageBagWithAssistantMessageMessageAndViolations()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturn(new ConstraintViolationList([
            new ConstraintViolation('foo', 'bar', [], '', '', '', 0, null),
        ]));

        $handler = new ValidationCapabilityHandler($validator);

        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionCode(0);
        $handler->handle(new MockAgent(), new MessageBag(Message::ofAssistant('Hello there')), [], new ValidationGroupOutputCapability(['foo']));
    }
}
