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
use Symfony\AI\Platform\Bridge\Cache\InputHasher;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Result\ToolCall;

final class InputHasherTest extends TestCase
{
    private InputHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new InputHasher();
    }

    public function testStringInputIsHashed()
    {
        $this->assertSame(md5('Hello'), $this->hasher->hash('Hello'));
    }

    public function testArrayInputIsHashed()
    {
        $input = ['foo' => 'bar', 'baz' => ['qux']];

        $this->assertSame(md5(json_encode($input, \JSON_THROW_ON_ERROR)), $this->hasher->hash($input));
    }

    public function testIdenticalMessageBagsBuiltSeparatelyProduceSameHash()
    {
        $first = new MessageBag(Message::ofUser('Hello there'));
        $second = new MessageBag(Message::ofUser('Hello there'));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testBareMessageProducesSameHashAsOneElementMessageBag()
    {
        $message = Message::ofUser('Hello there');

        $this->assertSame(
            $this->hasher->hash(new MessageBag($message)),
            $this->hasher->hash($message),
        );
    }

    public function testDifferentUserContentProducesDifferentHash()
    {
        $first = new MessageBag(Message::ofUser('Hello there'));
        $second = new MessageBag(Message::ofUser('Goodbye there'));

        $this->assertNotSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testDifferentSystemPromptProducesDifferentHash()
    {
        $first = new MessageBag(
            Message::forSystem('You are a helpful assistant.'),
            Message::ofUser('Hello there'),
        );
        $second = new MessageBag(
            Message::forSystem('You are a strict assistant.'),
            Message::ofUser('Hello there'),
        );

        $this->assertNotSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testDifferentAssistantTurnProducesDifferentHash()
    {
        $first = new MessageBag(
            Message::ofUser('Hello there'),
            Message::ofAssistant('First answer'),
            Message::ofUser('Follow up'),
        );
        $second = new MessageBag(
            Message::ofUser('Hello there'),
            Message::ofAssistant('Second answer'),
            Message::ofUser('Follow up'),
        );

        $this->assertNotSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testMessageOrderMatters()
    {
        $first = new MessageBag(Message::ofUser('A'), Message::ofUser('B'));
        $second = new MessageBag(Message::ofUser('B'), Message::ofUser('A'));

        $this->assertNotSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testTextContentIsStable()
    {
        $first = new MessageBag(Message::ofUser(new Text('Hello'), new Text('World')));
        $second = new MessageBag(Message::ofUser(new Text('Hello'), new Text('World')));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testTextSignatureDoesNotChangeHash()
    {
        // The cache key is content-based: the Text signature is provenance metadata (a provider
        // guard for replayed turns), not part of the logical content, so it must not affect the key.
        $withoutSignature = new MessageBag(Message::ofUser(new Text('Hello')));
        $withSignature = new MessageBag(Message::ofUser(new Text('Hello', 'sig')));

        $this->assertSame($this->hasher->hash($withoutSignature), $this->hasher->hash($withSignature));
    }

    public function testThinkingContentIsStable()
    {
        $first = new MessageBag(Message::ofAssistant(new Thinking('Reasoning', 'sig')));
        $second = new MessageBag(Message::ofAssistant(new Thinking('Reasoning', 'sig')));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testImageUrlContentIsStable()
    {
        $first = new MessageBag(Message::ofUser(new ImageUrl('https://example.com/image.png')));
        $second = new MessageBag(Message::ofUser(new ImageUrl('https://example.com/image.png')));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testDocumentUrlContentIsStable()
    {
        $first = new MessageBag(Message::ofUser(new DocumentUrl('https://example.com/doc.pdf')));
        $second = new MessageBag(Message::ofUser(new DocumentUrl('https://example.com/doc.pdf')));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testFileContentWithSameBytesProducesSameHashEvenFromSeparateInstances()
    {
        $fixture = \dirname(__DIR__, 6).'/fixtures/image.jpg';

        $first = new MessageBag(Message::ofUser(Image::fromFile($fixture)));
        $second = new MessageBag(Message::ofUser(Image::fromFile($fixture)));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testFileContentWithDifferentBytesProducesDifferentHash()
    {
        $image = new MessageBag(Message::ofUser(Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg')));
        $audio = new MessageBag(Message::ofUser(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3')));

        $this->assertNotSame($this->hasher->hash($image), $this->hasher->hash($audio));
    }

    public function testToolCallContentIsStable()
    {
        $toolCall = new ToolCall('call_1', 'find_movies', ['genre' => 'sci-fi']);

        $first = new MessageBag(Message::ofAssistant($toolCall));
        $second = new MessageBag(Message::ofAssistant(new ToolCall('call_1', 'find_movies', ['genre' => 'sci-fi'])));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testToolCallMessageIsStable()
    {
        $toolCall = new ToolCall('call_1', 'find_movies', ['genre' => 'sci-fi']);

        $first = new MessageBag(Message::ofToolCall($toolCall, 'The Matrix'));
        $second = new MessageBag(Message::ofToolCall(new ToolCall('call_1', 'find_movies', ['genre' => 'sci-fi']), 'The Matrix'));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testCollectionContentIsStableAndRecursive()
    {
        $collection = new Collection(new Text('Hello'), new Text('World'));

        $first = new MessageBag(Message::ofUser($collection));
        $second = new MessageBag(Message::ofUser(new Collection(new Text('Hello'), new Text('World'))));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testTemplateContentIsStable()
    {
        $first = new MessageBag(Message::forSystem(Template::string('You are a {role} assistant.')));
        $second = new MessageBag(Message::forSystem(Template::string('You are a {role} assistant.')));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testExecutableCodeContentIsStable()
    {
        $first = new MessageBag(Message::ofAssistant(new ExecutableCode('echo "hi";', 'php', 'exec_1')));
        $second = new MessageBag(Message::ofAssistant(new ExecutableCode('echo "hi";', 'php', 'exec_1')));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testCodeExecutionContentIsStable()
    {
        $first = new MessageBag(Message::ofAssistant(new CodeExecution(true, 'hi', 'exec_1')));
        $second = new MessageBag(Message::ofAssistant(new CodeExecution(true, 'hi', 'exec_1')));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }

    public function testUnsupportedInputTypeThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->hasher->hash(new \stdClass());
    }

    public function testUnsupportedContentTypeThrows()
    {
        $bag = new MessageBag(Message::ofUser($this->createStub(ContentInterface::class)));

        $this->expectException(InvalidArgumentException::class);

        $this->hasher->hash($bag);
    }

    public function testMessageBagWithoutUserMessageIsHashable()
    {
        $first = new MessageBag(Message::forSystem('Only a system message'));
        $second = new MessageBag(Message::forSystem('Only a system message'));

        $this->assertSame($this->hasher->hash($first), $this->hasher->hash($second));
    }
}
