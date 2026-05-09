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
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\StreamResult;

final class ChatStreamListenerTest extends TestCase
{
    public function testItAccumulatesStringChunks()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag();
        $messages->add(Message::ofUser('Hello'));

        $generator = (static function () {
            yield new TextDelta('I am ');
            yield new TextDelta('doing well!');
        })();

        $stream = new StreamResult($generator);
        $stream->addListener(new ChatStreamListener($messages, $store));

        $deltas = iterator_to_array($stream->getContent());

        $this->assertSame(['I am ', 'doing well!'], array_map(strval(...), $deltas)); /* @phpstan-ignore argument.type */

        $stored = $store->load();
        $this->assertCount(2, $stored);

        $assistantMessage = $stored->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('I am doing well!', $assistantMessage->asText());
    }

    public function testItIgnoresNonStringChunks()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag();
        $messages->add(Message::ofUser('Hello'));

        $generator = (static function () {
            yield new TextDelta('Hello ');
            yield new ThinkingDelta('thinking...');
            yield new TextDelta('World');
        })();

        $stream = new StreamResult($generator);
        $stream->addListener(new ChatStreamListener($messages, $store));

        iterator_to_array($stream->getContent());

        $stored = $store->load();
        $assistantMessage = $stored->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('Hello World', $assistantMessage->asText());
    }

    public function testItMergesMetadataOntoAssistantMessage()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag();
        $messages->add(Message::ofUser('Hello'));

        $generator = (static function () {
            yield new TextDelta('Response');
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
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
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
        $this->assertSame('chunk1chunk2', $assistantMessage->asText());
    }
}
