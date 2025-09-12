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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\PlatformInvokationEvent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

#[CoversClass(PlatformInvokationEvent::class)]
final class PlatformInvokationEventTest extends TestCase
{
    public function testGettersReturnCorrectValues(): void
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        $input = 'Hello, world!';
        $options = ['temperature' => 0.7];

        $event = new PlatformInvokationEvent($model, $input, $options);

        $this->assertSame($model, $event->model);
        $this->assertSame($input, $event->input);
        $this->assertSame($options, $event->options);
    }

    public function testSetInputChangesInput(): void
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        $originalInput = 'Hello, world!';
        $newInput = new MessageBag(Message::ofUser('Hello, world!'));

        $event = new PlatformInvokationEvent($model, $originalInput);
        $event->input = $newInput;

        $this->assertSame($newInput, $event->input);
    }

    public function testWorksWithDifferentInputTypes(): void
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        // Test with string
        $stringEvent = new PlatformInvokationEvent($model, 'string input');
        $this->assertIsString($stringEvent->input);

        // Test with array
        $arrayEvent = new PlatformInvokationEvent($model, ['key' => 'value']);
        $this->assertIsArray($arrayEvent->input);

        // Test with object
        $objectInput = new MessageBag();
        $objectEvent = new PlatformInvokationEvent($model, $objectInput);
        $this->assertSame($objectInput, $objectEvent->input);
    }
}
