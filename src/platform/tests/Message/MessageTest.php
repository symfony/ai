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
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;

final class MessageTest extends TestCase
{
    public function testCreateSystemMessageWithString()
    {
        $message = Message::forSystem('My amazing system prompt.');

        $this->assertSame('My amazing system prompt.', $message->getContent());
    }

    public function testCreateSystemMessageWithStringable()
    {
        $message = Message::forSystem(new class implements \Stringable {
            public function __toString(): string
            {
                return 'My amazing system prompt.';
            }
        });

        $this->assertSame('My amazing system prompt.', $message->getContent());
    }

    public function testCreateAssistantMessage()
    {
        $message = Message::ofAssistant('It is time to sleep.');

        $this->assertSame('It is time to sleep.', $message->getContent());
    }

    public function testCreateAssistantMessageWithToolCalls()
    {
        $toolCalls = [
            new ToolCall('call_123456', 'my_tool', ['foo' => 'bar']),
            new ToolCall('call_456789', 'my_faster_tool'),
        ];
        $message = Message::ofAssistant(toolCalls: $toolCalls);

        $this->assertCount(2, $message->getToolCalls());
        $this->assertTrue($message->hasToolCalls());
    }

    public function testCreateUserMessageWithString()
    {
        $message = Message::ofUser('Hi, my name is John.');

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
        $this->assertSame('Hi, my name is John.', $message->getContent()[0]->getText());
    }

    public function testCreateUserMessageWithStringable()
    {
        $message = Message::ofUser(new class implements \Stringable {
            public function __toString(): string
            {
                return 'Hi, my name is John.';
            }
        });

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
        $this->assertSame('Hi, my name is John.', $message->getContent()[0]->getText());
    }

    public function testCreateUserMessageContentInterfaceImplementingStringable()
    {
        $message = Message::ofUser(new class implements ContentInterface, \Stringable {
            public function __toString(): string
            {
                return 'I am a ContentInterface!';
            }
        });

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(ContentInterface::class, $message->getContent()[0]);
    }

    public function testCreateUserMessageWithTextContent()
    {
        $text = new Text('Hi, my name is John.');
        $message = Message::ofUser($text);

        $this->assertSame([$text], $message->getContent());
    }

    public function testCreateUserMessageWithImages()
    {
        $message = Message::ofUser(
            new Text('Hi, my name is John.'),
            new ImageUrl('http://images.local/my-image.png'),
            'The following image is a joke.',
            new ImageUrl('http://images.local/my-image2.png'),
        );

        $this->assertCount(4, $message->getContent());
    }

    public function testCreateToolCallMessage()
    {
        $toolCall = new ToolCall('call_123456', 'my_tool', ['foo' => 'bar']);
        $message = Message::ofToolCall($toolCall, 'Foo bar.');

        $this->assertSame('Foo bar.', $message->getContent());
        $this->assertSame($toolCall, $message->getToolCall());
    }

    public function testCreateUserMessageWithObjectSerializesItAsContext()
    {
        $city = new City(name: 'Berlin', population: 3500000);
        $message = Message::ofUser('Please research missing data', $city);

        $content = $message->getContent();
        $this->assertCount(2, $content);

        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertSame('Please research missing data', $content[0]->getText());

        $this->assertInstanceOf(Text::class, $content[1]);
        $serializedContent = $content[1]->getText();
        $this->assertStringContainsString('Berlin', $serializedContent);
        $this->assertStringContainsString('3500000', $serializedContent);
        $this->assertStringContainsString('"name"', $serializedContent);
        $this->assertStringContainsString('"population"', $serializedContent);
    }

    public function testCreateUserMessageWithObjectStoresItInMetadata()
    {
        $city = new City(name: 'Berlin');
        $message = Message::ofUser('Research data', $city);

        $this->assertTrue($message->getMetadata()->has('structured_output_object'));
        $this->assertSame($city, $message->getMetadata()->get('structured_output_object'));
    }

    public function testCreateUserMessageWithMultipleContentAndObject()
    {
        $city = new City(name: 'Paris');
        $message = Message::ofUser(
            'Check this city',
            new ImageUrl('http://example.com/paris.jpg'),
            $city
        );

        $content = $message->getContent();
        $this->assertCount(3, $content);

        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertInstanceOf(ImageUrl::class, $content[1]);

        $this->assertInstanceOf(Text::class, $content[2]);
        $this->assertStringContainsString('Paris', $content[2]->getText());
    }

    public function testCreateUserMessageWithOnlyObject()
    {
        $city = new City(name: 'Tokyo');
        $message = Message::ofUser($city);

        $content = $message->getContent();
        $this->assertCount(1, $content);

        $this->assertInstanceOf(Text::class, $content[0]);
        $serializedContent = $content[0]->getText();
        $this->assertStringContainsString('Tokyo', $serializedContent);
        $this->assertStringContainsString('"name"', $serializedContent);
    }

    public function testCreateUserMessageWithStringableIsNotTreatedAsObject()
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'I am stringable';
            }
        };

        $message = Message::ofUser('Hello', $stringable);

        $content = $message->getContent();
        $this->assertCount(2, $content);

        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertInstanceOf(Text::class, $content[1]);
        $this->assertSame('I am stringable', $content[1]->getText());

        $this->assertFalse($message->getMetadata()->has('structured_output_object'));
    }

    public function testCreateUserMessageWithContentInterfaceIsNotTreatedAsObject()
    {
        $imageUrl = new ImageUrl('http://example.com/image.jpg');
        $message = Message::ofUser('Look at this', $imageUrl);

        $content = $message->getContent();
        $this->assertCount(2, $content);

        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertInstanceOf(ImageUrl::class, $content[1]);

        $this->assertFalse($message->getMetadata()->has('structured_output_object'));
    }

    public function testCreateUserMessageThrowsExceptionForInvalidContentType()
    {
        $this->expectException(\Symfony\AI\Platform\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content must be string, Stringable, or ContentInterface');

        Message::ofUser('Hello', ['invalid' => 'array']);
    }

    public function testCreateUserMessageWithObjectAndInvalidTypeThrowsException()
    {
        $this->expectException(\Symfony\AI\Platform\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content must be string, Stringable, or ContentInterface');

        $city = new City(name: 'London');
        Message::ofUser(123, 'text', $city);
    }
}
