<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cache\MessageBagCacheKeyGenerator;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;

final class MessageBagCacheKeyGeneratorTest extends TestCase
{
    private MessageBagCacheKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MessageBagCacheKeyGenerator();
    }

    public function testGeneratorSupportsMessageBag()
    {
        $this->assertTrue($this->generator->supports(new MessageBag()));
        $this->assertFalse($this->generator->supports(new \stdClass()));
    }

    public function testGeneratorThrowsOnUnsupportedInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" only supports "%s" inputs, "stdClass" given.', MessageBagCacheKeyGenerator::class, MessageBag::class));

        $this->generator->generate(new \stdClass());
    }

    public function testGeneratorReturnsSameKeyForSameMessageBag()
    {
        $messageBag = new MessageBag(
            Message::forSystem('You are a helpful assistant'),
            Message::ofUser('Hello there'),
        );

        $this->assertSame($this->generator->generate($messageBag), $this->generator->generate($messageBag));
    }

    public function testGeneratorReturnsSameKeyForSeparateMessageBagsWithSameContent()
    {
        $messageBag = new MessageBag(
            Message::forSystem('You are a helpful assistant'),
            Message::ofUser('Hello there'),
            Message::ofAssistant('General Kenobi'),
        );

        $secondMessageBag = new MessageBag(
            Message::forSystem('You are a helpful assistant'),
            Message::ofUser('Hello there'),
            Message::ofAssistant('General Kenobi'),
        );

        $this->assertSame($this->generator->generate($messageBag), $this->generator->generate($secondMessageBag));
    }

    public function testGeneratorReturnsDifferentKeyForDifferentContent()
    {
        $messageBag = new MessageBag(Message::ofUser('Hello there'));
        $secondMessageBag = new MessageBag(Message::ofUser('Hello again'));

        $this->assertNotSame($this->generator->generate($messageBag), $this->generator->generate($secondMessageBag));
    }

    public function testGeneratorReturnsDifferentKeyForDifferentMessageOrder()
    {
        $messageBag = new MessageBag(Message::ofUser('Hello there'), Message::ofUser('Hello again'));
        $secondMessageBag = new MessageBag(Message::ofUser('Hello again'), Message::ofUser('Hello there'));

        $this->assertNotSame($this->generator->generate($messageBag), $this->generator->generate($secondMessageBag));
    }

    public function testGeneratorReturnsDifferentKeyForDifferentRole()
    {
        $messageBag = new MessageBag(Message::ofUser('Hello there'));
        $secondMessageBag = new MessageBag(Message::ofAssistant('Hello there'));

        $this->assertNotSame($this->generator->generate($messageBag), $this->generator->generate($secondMessageBag));
    }

    public function testGeneratorKeysImageUrlContent()
    {
        $messageBag = new MessageBag(Message::ofUser(new Text('What is on it?'), new ImageUrl('https://example.com/first.png')));
        $sameMessageBag = new MessageBag(Message::ofUser(new Text('What is on it?'), new ImageUrl('https://example.com/first.png')));
        $otherMessageBag = new MessageBag(Message::ofUser(new Text('What is on it?'), new ImageUrl('https://example.com/second.png')));

        $this->assertSame($this->generator->generate($messageBag), $this->generator->generate($sameMessageBag));
        $this->assertNotSame($this->generator->generate($messageBag), $this->generator->generate($otherMessageBag));
    }

    public function testGeneratorKeysBinaryContentOnItsBytes()
    {
        $messageBag = new MessageBag(Message::ofUser(new Image(static fn (): string => 'first-image', 'image/png')));
        $sameMessageBag = new MessageBag(Message::ofUser(new Image('first-image', 'image/png')));
        $otherMessageBag = new MessageBag(Message::ofUser(new Image('second-image', 'image/png')));

        $this->assertSame($this->generator->generate($messageBag), $this->generator->generate($sameMessageBag));
        $this->assertNotSame($this->generator->generate($messageBag), $this->generator->generate($otherMessageBag));
    }

    public function testGeneratorKeysToolCallAndThinkingContent()
    {
        $messageBag = new MessageBag(Message::ofAssistant(new Thinking('Let me check'), new ToolCall('call_1', 'weather', ['city' => 'Paris'])));
        $sameMessageBag = new MessageBag(Message::ofAssistant(new Thinking('Let me check'), new ToolCall('call_1', 'weather', ['city' => 'Paris'])));
        $otherMessageBag = new MessageBag(Message::ofAssistant(new Thinking('Let me check'), new ToolCall('call_1', 'weather', ['city' => 'Berlin'])));

        $this->assertSame($this->generator->generate($messageBag), $this->generator->generate($sameMessageBag));
        $this->assertNotSame($this->generator->generate($messageBag), $this->generator->generate($otherMessageBag));
    }

    public function testGeneratorIgnoresMessageMetadata()
    {
        $messageBag = new MessageBag(Message::ofUser('Hello there'));

        $key = $this->generator->generate($messageBag);
        $messageBag->getMetadata()->set(['request_id' => 'abc']);
        $messageBag->getMessages()[0]->getMetadata()->set(['request_id' => 'abc']);

        $this->assertSame($key, $this->generator->generate($messageBag));
    }
}
