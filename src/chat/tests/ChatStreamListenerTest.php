<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\ChatStreamListener;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\StreamResult;

final class ChatStreamListenerTest extends TestCase
{
    public function testItAccumulatesStringChunks()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag();
        $messages->add(Message::ofUser('Hello'));

        $generator = (static function () {
            yield 'I am ';
            yield 'doing well!';
        })();

        $stream = new StreamResult($generator);
        $stream->addListener(new ChatStreamListener($messages, $store));

        $chunks = iterator_to_array($stream->getContent());

        $this->assertSame(['I am ', 'doing well!'], $chunks);

        $stored = $store->load();
        $this->assertCount(2, $stored);

        $assistantMessage = $stored->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('I am doing well!', $assistantMessage->getContent());
    }

    public function testItIgnoresNonStringChunks()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag();
        $messages->add(Message::ofUser('Hello'));

        $generator = (static function () {
            yield 'Hello ';
            yield new \stdClass();
            yield 'World';
        })();

        $stream = new StreamResult($generator);
        $stream->addListener(new ChatStreamListener($messages, $store));

        iterator_to_array($stream->getContent());

        $stored = $store->load();
        $assistantMessage = $stored->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('Hello World', $assistantMessage->getContent());
    }

    public function testItMergesMetadataOntoAssistantMessage()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag();
        $messages->add(Message::ofUser('Hello'));

        $generator = (static function () {
            yield 'Response';
        })();

        $stream = new StreamResult($generator);
        $stream->getMetadata()->add('model', 'gpt-4');
        $stream->addListener(new ChatStreamListener($messages, $store));

        iterator_to_array($stream->getContent());

        $stored = $store->load();
        $assistantMessage = $stored->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('gpt-4', $assistantMessage->getMetadata()->get('model'));
    }

    public function testItSavesToStoreOnComplete()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag();
        $messages->add(Message::ofUser('Hello'));

        $generator = (static function () {
            yield 'chunk1';
            yield 'chunk2';
        })();

        $stream = new StreamResult($generator);
        $stream->addListener(new ChatStreamListener($messages, $store));

        // Before consuming the stream, store should be empty
        $this->assertCount(0, $store->load());

        iterator_to_array($stream->getContent());

        // After consuming, store should have user + assistant messages
        $stored = $store->load();
        $this->assertCount(2, $stored);

        $assistantMessage = $stored->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('chunk1chunk2', $assistantMessage->getContent());
    }
}
