<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Event\ModelRoutingEvent;

final class ModelRoutingEventTest extends TestCase
{
    public function testGettersReturnConstructorValues()
    {
        $event = new ModelRoutingEvent('gpt-4o', 'Hello', ['temperature' => 0.7]);

        $this->assertSame('gpt-4o', $event->getModel());
        $this->assertSame('Hello', $event->getInput());
        $this->assertSame(['temperature' => 0.7], $event->getOptions());
    }

    public function testSetModel()
    {
        $event = new ModelRoutingEvent('gpt-4o', 'Hello');

        $event->setModel('claude-3-5-sonnet');

        $this->assertSame('claude-3-5-sonnet', $event->getModel());
    }

    public function testSetInput()
    {
        $event = new ModelRoutingEvent('gpt-4o', 'Hello');

        $event->setInput('Goodbye');

        $this->assertSame('Goodbye', $event->getInput());
    }

    public function testSetOptions()
    {
        $event = new ModelRoutingEvent('gpt-4o', 'Hello');

        $event->setOptions(['max_tokens' => 100]);

        $this->assertSame(['max_tokens' => 100], $event->getOptions());
    }
}
