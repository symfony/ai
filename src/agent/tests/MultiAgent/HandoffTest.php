<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\MultiAgent;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\MultiAgent\Handoff;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class HandoffTest extends TestCase
{
    public function testConstructorWithValidConditions()
    {
        $agent = new MockAgent(name: 'technical');
        $when = ['code', 'debug', 'programming'];

        $handoff = new Handoff($agent, $when);

        $this->assertSame($agent, $handoff->getTo());
        $this->assertSame($when, $handoff->getWhen());
    }

    public function testConstructorWithSingleCondition()
    {
        $agent = new MockAgent(name: 'error-handler');
        $when = ['error'];

        $handoff = new Handoff($agent, $when);

        $this->assertSame($agent, $handoff->getTo());
        $this->assertSame($when, $handoff->getWhen());
    }

    public function testConstructorThrowsExceptionForEmptyWhenArray()
    {
        $agent = new MockAgent();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Handoff must have at least one "when" condition');

        new Handoff($agent, []);
    }

    public function testGetToReturnsAgent()
    {
        $agent = new MockAgent(name: 'technical');

        $handoff = new Handoff($agent, ['code']);

        $this->assertSame($agent, $handoff->getTo());
        $this->assertSame('technical', $handoff->getTo()->getName());
    }

    public function testGetWhenReturnsConditions()
    {
        $agent = new MockAgent(name: 'billing');
        $when = ['payment', 'billing', 'invoice', 'subscription'];

        $handoff = new Handoff($agent, $when);

        $this->assertSame($when, $handoff->getWhen());
    }
}
