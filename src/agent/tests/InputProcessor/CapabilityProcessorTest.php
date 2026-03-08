<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\InputProcessor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Capability\CapabilityHandlerRegistry;
use Symfony\AI\Agent\Capability\DelayCapabilityHandler;
use Symfony\AI\Agent\Capability\InputDelayCapability;
use Symfony\AI\Agent\Capability\OutputDelayCapability;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\CapabilityProcessor;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MockClock;

final class CapabilityProcessorTest extends TestCase
{
    public function testProcessorCannotProcessInputWithoutHandlers()
    {
        $processor = new CapabilityProcessor(new CapabilityHandlerRegistry([]));
        $processor->setAgent(new MockAgent());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No capability handler found for the "Symfony\AI\Agent\Capability\InputDelayCapability" capability.');
        $this->expectExceptionCode(0);
        $processor->processInput(new Input('foo', new MessageBag(
            Message::ofUser('Hello there'),
        ), capabilities: [
            new InputDelayCapability(10),
        ]));
    }

    public function testProcessorCanProcessInput()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $processor = new CapabilityProcessor(new CapabilityHandlerRegistry([
            new DelayCapabilityHandler($clock),
        ]));
        $processor->setAgent(new MockAgent());

        $processor->processInput(new Input('foo', new MessageBag(
            Message::ofUser('Hello there'),
        ), capabilities: [
            new InputDelayCapability(10),
        ]));

        $this->assertSame('2020-01-01 10:00:10', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testProcessorCannotProcessOutputWithoutHandlers()
    {
        $processor = new CapabilityProcessor(new CapabilityHandlerRegistry([]));
        $processor->setAgent(new MockAgent());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No capability handler found for the "Symfony\AI\Agent\Capability\OutputDelayCapability" capability.');
        $this->expectExceptionCode(0);
        $processor->processOutput(new Output('foo', new TextResult('foo'), new MessageBag(
            Message::ofUser('Hello there'),
        ), capabilities: [
            new OutputDelayCapability(10),
        ]));
    }

    public function testProcessorCanProcessOutput()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $processor = new CapabilityProcessor(new CapabilityHandlerRegistry([
            new DelayCapabilityHandler($clock),
        ]));
        $processor->setAgent(new MockAgent());

        $processor->processOutput(new Output('foo', new TextResult('foo'), new MessageBag(
            Message::ofUser('Hello there'),
        ), capabilities: [
            new OutputDelayCapability(10),
        ]));

        $this->assertSame('2020-01-01 10:00:10', $clock->now()->format('Y-m-d H:i:s'));
    }
}
