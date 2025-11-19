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
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

final class InvocationEventTest extends TestCase
{
    public function testGettersReturnCorrectValues()
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        $input = 'Hello, world!';
        $options = ['temperature' => 0.7];

        $event = new InvocationEvent($model, $input, $options);

        $this->assertSame($model, $event->getModel());
        $this->assertSame($input, $event->getInput());
        $this->assertSame($options, $event->getOptions());
    }

    public function testSetInputChangesInput()
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        $originalInput = 'Hello, world!';
        $newInput = new MessageBag(Message::ofUser('Hello, world!'));

        $event = new InvocationEvent($model, $originalInput);
        $event->setInput($newInput);

        $this->assertSame($newInput, $event->getInput());
    }

    public function testWorksWithDifferentInputTypes()
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        // Test with string
        $stringEvent = new InvocationEvent($model, 'string input');
        $this->assertIsString($stringEvent->getInput());

        // Test with array
        $arrayEvent = new InvocationEvent($model, ['key' => 'value']);
        $this->assertIsArray($arrayEvent->getInput());

        // Test with object
        $objectInput = new MessageBag();
        $objectEvent = new InvocationEvent($model, $objectInput);
        $this->assertSame($objectInput, $objectEvent->getInput());
    }
}
