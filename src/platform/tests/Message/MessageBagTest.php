<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;

final class MessageBagTest extends TestCase
{
    public function testGetSystemMessage()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
            Message::ofToolCall(new ToolCall('tool', 'tool_name', ['param' => 'value']), 'Yes, go sleeping.'),
        );

        $systemMessage = $messageBag->getSystemMessage();

        $this->assertSame('My amazing system prompt.', $systemMessage->getContent());
    }

    public function testGetSystemMessageWithoutSystemMessage()
    {
        $messageBag = new MessageBag(
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
            Message::ofToolCall(new ToolCall('tool', 'tool_name', ['param' => 'value']), 'Yes, go sleeping.'),
        );

        $this->assertNull($messageBag->getSystemMessage());
    }

    public function testWith()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
        );

        $newMessage = Message::ofAssistant('It is time to wake up.');
        $newMessageBag = $messageBag->with($newMessage);

        $this->assertCount(3, $messageBag);
        $this->assertCount(4, $newMessageBag);

        $newMessageFromBag = $newMessageBag->getMessages()[3];

        $this->assertInstanceOf(AssistantMessage::class, $newMessageFromBag);
        $this->assertSame('It is time to wake up.', $newMessageFromBag->getContent());
    }

    public function testMerge()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
        );

        $messageBag = $messageBag->merge(new MessageBag(
            Message::ofAssistant('It is time to wake up.')
        ));

        $this->assertCount(4, $messageBag);

        $messageFromBag = $messageBag->getMessages()[3];

        $this->assertInstanceOf(AssistantMessage::class, $messageFromBag);
        $this->assertSame('It is time to wake up.', $messageFromBag->getContent());
    }

    public function testWithoutSystemMessage()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofAssistant('It is time to sleep.'),
            Message::forSystem('A system prompt in the middle.'),
            Message::ofUser('Hello, world!'),
            Message::forSystem('Another system prompt at the end'),
        );

        $newMessageBag = $messageBag->withoutSystemMessage();

        $this->assertCount(5, $messageBag);
        $this->assertCount(2, $newMessageBag);

        $assistantMessage = $newMessageBag->getMessages()[0];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('It is time to sleep.', $assistantMessage->getContent());

        $userMessage = $newMessageBag->getMessages()[1];
        $this->assertInstanceOf(UserMessage::class, $userMessage);
        $this->assertInstanceOf(Text::class, $userMessage->getContent()[0]);
        $this->assertSame('Hello, world!', $userMessage->getContent()[0]->getText());
    }

    public function testWithSystemMessage()
    {
        $messageBag = new MessageBag(
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
        );

        $newMessage = Message::forSystem('My amazing system prompt.');
        $newMessageBag = $messageBag->withSystemMessage($newMessage);

        $this->assertCount(2, $messageBag);
        $this->assertCount(3, $newMessageBag);

        $newMessageBagMessage = $newMessageBag->getMessages()[0];

        $this->assertInstanceOf(SystemMessage::class, $newMessageBagMessage);
        $this->assertSame('My amazing system prompt.', $newMessageBagMessage->getContent());
    }

    public function testContainsImageReturnsFalseWithoutImage()
    {
        $messageBag = new MessageBag(
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
        );

        $this->assertFalse($messageBag->containsImage());
    }

    public function testContainsImageReturnsTrueWithImage()
    {
        $messageBag = new MessageBag(
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
            Message::ofUser('My hint for how to analyze an image.', new ImageUrl('http://image-generator.local/my-fancy-image.png')),
        );

        $this->assertTrue($messageBag->containsImage());
    }

    public function testItCanHandleMetadata()
    {
        $messageBag = new MessageBag();
        $metadata = $messageBag->getMetadata();

        $this->assertCount(0, $metadata);

        $metadata->add('key', 'value');
        $metadata = $messageBag->getMetadata();

        $this->assertCount(1, $metadata);
    }

    public function testGetUserMessage()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofAssistant('It is time to sleep.'),
            Message::ofUser('Hello, world!'),
            Message::ofAssistant('How can I help you?'),
        );

        $userMessage = $messageBag->getUserMessage();

        $this->assertInstanceOf(UserMessage::class, $userMessage);
        $this->assertInstanceOf(Text::class, $userMessage->getContent()[0]);
        $this->assertSame('Hello, world!', $userMessage->getContent()[0]->getText());
    }

    public function testGetUserMessageReturnsNullWithoutUserMessage()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofAssistant('It is time to sleep.'),
        );

        $this->assertNull($messageBag->getUserMessage());
    }

    public function testGetUserMessageReturnsFirstUserMessage()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofUser('First user message'),
            Message::ofAssistant('Response'),
            Message::ofUser('Second user message'),
        );

        $userMessage = $messageBag->getUserMessage();

        $this->assertInstanceOf(UserMessage::class, $userMessage);
        $this->assertInstanceOf(Text::class, $userMessage->getContent()[0]);
        $this->assertSame('First user message', $userMessage->getContent()[0]->getText());
    }

    public function testGetUserMessageText()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofUser('Hello, world!'),
            Message::ofAssistant('How can I help you?'),
        );

        $userMessage = $messageBag->getUserMessage();
        $userText = $userMessage?->asText();

        $this->assertSame('Hello, world!', $userText);
    }

    public function testGetUserMessageTextReturnsNullWithoutUserMessage()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofAssistant('It is time to sleep.'),
        );

        $userMessage = $messageBag->getUserMessage();

        $this->assertNull($userMessage?->asText());
    }

    public function testGetUserMessageTextWithMultipleTextParts()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofUser('Part one', 'Part two', 'Part three'),
            Message::ofAssistant('Response'),
        );

        $userMessage = $messageBag->getUserMessage();
        $userText = $userMessage?->asText();

        $this->assertSame('Part one Part two Part three', $userText);
    }

    public function testGetUserMessageTextIgnoresNonTextContent()
    {
        $messageBag = new MessageBag(
            Message::forSystem('My amazing system prompt.'),
            Message::ofUser('Text content', new ImageUrl('http://example.com/image.png')),
            Message::ofAssistant('Response'),
        );

        $userMessage = $messageBag->getUserMessage();
        $userText = $userMessage?->asText();

        // Should only return the text content, ignoring the image
        $this->assertSame('Text content', $userText);
    }

    public function testGetIterator()
    {
        $systemMessage = Message::forSystem('My amazing system prompt.');
        $assistantMessage = Message::ofAssistant('It is time to sleep.');
        $userMessage = Message::ofUser('Hello, world!');

        $messageBag = new MessageBag($systemMessage, $assistantMessage, $userMessage);

        $iterator = $messageBag->getIterator();

        $this->assertInstanceOf(\ArrayIterator::class, $iterator);
        $this->assertCount(3, $iterator);

        $messages = iterator_to_array($iterator);
        $this->assertSame($systemMessage, $messages[0]);
        $this->assertSame($assistantMessage, $messages[1]);
        $this->assertSame($userMessage, $messages[2]);
    }

    public function testMessageBagIsIterable()
    {
        $systemMessage = Message::forSystem('My amazing system prompt.');
        $assistantMessage = Message::ofAssistant('It is time to sleep.');
        $userMessage = Message::ofUser('Hello, world!');

        $messageBag = new MessageBag($systemMessage, $assistantMessage, $userMessage);

        $collectedMessages = [];
        foreach ($messageBag as $index => $message) {
            $collectedMessages[$index] = $message;
        }

        $this->assertCount(3, $collectedMessages);
        $this->assertSame($systemMessage, $collectedMessages[0]);
        $this->assertSame($assistantMessage, $collectedMessages[1]);
        $this->assertSame($userMessage, $collectedMessages[2]);
    }
}
