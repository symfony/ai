<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\EventListener\StringToMessageBagListener;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;

final class StringToMessageBagListenerTest extends TestCase
{
    public function testConvertsStringInputToMessageBagForMessagesCapableModel()
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        $event = new InvocationEvent($model, 'Hello, world!');
        $listener = new StringToMessageBagListener();

        $listener($event);

        $this->assertInstanceOf(MessageBag::class, $event->getInput());
        $this->assertCount(1, $event->getInput()->getMessages());
        $message = $event->getInput()->getMessages()[0];
        $this->assertInstanceOf(UserMessage::class, $message);
        $this->assertCount(1, $message->getContent());
        $content = $message->getContent()[0];
        $this->assertInstanceOf(Text::class, $content);
        $this->assertSame('Hello, world!', $content->getText());
    }

    public function testDoesNotConvertStringInputForNonMessagesCapableModel()
    {
        $model = new class('test-model', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]) extends Model {
        };

        $originalInput = 'Hello, world!';
        $event = new InvocationEvent($model, $originalInput);
        $listener = new StringToMessageBagListener();

        $listener($event);

        $this->assertSame($originalInput, $event->getInput());
    }

    public function testDoesNotConvertNonStringInput()
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        $originalInput = new MessageBag(Message::ofUser('Hello'));
        $event = new InvocationEvent($model, $originalInput);
        $listener = new StringToMessageBagListener();

        $listener($event);

        $this->assertSame($originalInput, $event->getInput());
    }

    public function testDoesNotConvertArrayInput()
    {
        $model = new class('test-model', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]) extends Model {
        };

        $originalInput = ['key' => 'value'];
        $event = new InvocationEvent($model, $originalInput);
        $listener = new StringToMessageBagListener();

        $listener($event);

        $this->assertSame($originalInput, $event->getInput());
    }
}
