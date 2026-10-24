<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Serializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Commentary;
use Symfony\AI\Platform\Message\Content\ComputerCall;
use Symfony\AI\Platform\Message\Content\CustomToolCall;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\FileSearch;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\LocalShellCall;
use Symfony\AI\Platform\Message\Content\McpApprovalRequest;
use Symfony\AI\Platform\Message\Content\McpCall;
use Symfony\AI\Platform\Message\Content\McpListTools;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Serializer\MessageNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageNormalizerTest extends TestCase
{
    #[DataProvider('provideMessages')]
    public function testRoundTripIsLossless(MessageInterface $message)
    {
        $message->getMetadata()->add('token_usage', ['prompt_tokens' => 10]);
        $serializer = new Serializer([new ArrayDenormalizer(), new MessageNormalizer()], [new JsonEncoder()]);

        $restored = $serializer->deserialize($serializer->serialize([$message], 'json'), MessageInterface::class.'[]', 'json');

        $this->assertEquals([$message], $restored);
    }

    /**
     * @return iterable<string, array{MessageInterface}>
     */
    public static function provideMessages(): iterable
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin'], 'tool-signature');

        yield 'system' => [Message::forSystem('You are a helpful assistant.')];
        yield 'system with template' => [Message::forSystem(Template::string('You are a {role} assistant.'))];
        yield 'user with text' => [Message::ofUser('Hello')];
        yield 'user with template' => [Message::ofUser('Hello', Template::expression('name'))];
        yield 'user with files' => [Message::ofUser(
            'Look at these',
            Image::fromDataUrl('data:image/png;base64,aW1hZ2U='),
            Audio::fromDataUrl('data:audio/mpeg;base64,YXVkaW8='),
            Document::fromDataUrl('data:application/pdf;base64,ZG9j'),
            new ImageUrl('https://example.com/image.png'),
            new DocumentUrl('https://example.com/document.pdf'),
        )];
        yield 'user with collection' => [new UserMessage(new Collection(new Text('Hello'), new ImageUrl('https://example.com/image.png')))];
        yield 'assistant with text signature' => [new AssistantMessage(new Text('Hi', 'text-signature'))];
        yield 'assistant with text phase' => [new AssistantMessage(new Text('Answer.', null, 'final_answer'), new Text('Plain.'))];
        yield 'assistant with thinking and tool call' => [new AssistantMessage(
            new Thinking('Let me check', 'thinking-signature'),
            new Commentary('Looking up the weather now.'),
            new Text('Checking the weather'),
            $toolCall,
        )];
        yield 'assistant with server tool parts' => [new AssistantMessage(
            new ExecutableCode('print(1)', 'python', 'code_1'),
            new CodeExecution(true, '1', 'exec_1'),
            new WebSearch('symfony', 'ws_1', 'completed', ['symfony', 'symfony ai'], 'ws-signature'),
            new FileSearch(['query'], [['file_id' => 'file_1', 'filename' => 'a.txt', 'text' => 'snippet', 'score' => 0.5, 'attributes' => []]], 'fs_1', 'completed'),
            new McpCall('server', 'tool', '{"a":1}', 'output', null, 'mcp_1', 'completed'),
            new McpListTools('server', [['name' => 'tool', 'description' => 'A tool', 'input_schema' => ['type' => 'object']]], 'mcpl_1'),
            new McpApprovalRequest('server', 'tool', '{"a":1}', 'mcpr_1'),
            new CustomToolCall('grammar', 'input', 'ctc_1', 'completed'),
            new ComputerCall(['type' => 'click', 'x' => 1, 'y' => 2], 'call_2', [['id' => 'sc_1', 'code' => 'malicious', 'message' => 'Careful']], 'cu_1', 'completed'),
            new LocalShellCall(['ls', '-la'], 'call_3', 'lsh_1', 'completed'),
        )];
        yield 'tool call' => [new ToolCallMessage($toolCall, new Text('Sunny'))];
        yield 'multimodal tool call' => [new ToolCallMessage($toolCall, new Text('Chart'), Image::fromDataUrl('data:image/png;base64,aW1hZ2U='))];
    }

    public function testItKeepsAddedAtStableAndDerivedFromTheId()
    {
        $message = Message::ofUser('Hello');
        $normalizer = new MessageNormalizer();

        $normalized = $normalizer->normalize($message);

        $this->assertSame($message->getId()->getDateTime()->getTimestamp(), $normalized['addedAt']);
        $this->assertSame($normalized, $normalizer->normalize($message));
        $this->assertFalse($normalizer->denormalize($normalized, MessageInterface::class)->getMetadata()->has('addedAt'));
    }

    public function testItSupportsCustomIdentifier()
    {
        $message = Message::ofUser('Hello');
        $normalizer = new MessageNormalizer();

        $normalized = $normalizer->normalize($message, context: ['identifier' => '_id']);

        $this->assertSame($message->getId()->toRfc4122(), $normalized['_id']);
        $this->assertArrayNotHasKey('id', $normalized);
        $this->assertTrue($message->getId()->equals($normalizer->denormalize($normalized, MessageInterface::class, context: ['identifier' => '_id'])->getId()));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('provideLegacyMessages')]
    public function testItDenormalizesTheLegacyFormat(array $data, MessageInterface $expected)
    {
        $message = (new MessageNormalizer())->denormalize($data, MessageInterface::class);

        $this->assertEquals($expected->withId($message->getId()), $message);
        $this->assertSame('01960d1f-7a0e-7c55-8a0e-2b3c4d5e6f70', $message->getId()->toRfc4122());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, MessageInterface}>
     */
    public static function provideLegacyMessages(): iterable
    {
        $base = ['id' => '01960d1f-7a0e-7c55-8a0e-2b3c4d5e6f70', 'metadata' => [], 'addedAt' => 1761553508];
        $toolCall = ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Berlin"}']];

        yield 'system' => [
            $base + ['type' => SystemMessage::class, 'content' => 'You are a helpful assistant.', 'contentAsBase64' => [], 'toolsCalls' => [], 'parts' => []],
            new SystemMessage('You are a helpful assistant.'),
        ];
        yield 'user' => [
            $base + ['type' => UserMessage::class, 'content' => '', 'contentAsBase64' => [
                ['type' => Text::class, 'content' => 'Hello'],
                ['type' => ImageUrl::class, 'content' => 'https://example.com/image.png'],
                ['type' => Image::class, 'content' => 'data:image/png;base64,aW1hZ2U='],
            ], 'toolsCalls' => [], 'parts' => []],
            new UserMessage(new Text('Hello'), new ImageUrl('https://example.com/image.png'), Image::fromDataUrl('data:image/png;base64,aW1hZ2U=')),
        ];
        yield 'assistant with parts' => [
            $base + ['type' => AssistantMessage::class, 'content' => 'Checking', 'contentAsBase64' => [], 'toolsCalls' => [$toolCall], 'parts' => [
                ['type' => Thinking::class, 'content' => 'Hmm', 'signature' => 'sig'],
                ['type' => Text::class, 'text' => 'Checking'],
                ['type' => ToolCall::class, 'toolCall' => $toolCall, 'signature' => 'tool-sig'],
            ]],
            new AssistantMessage(new Thinking('Hmm', 'sig'), new Text('Checking'), new ToolCall('call_1', 'weather', ['city' => 'Berlin'], 'tool-sig')),
        ];
        yield 'assistant without parts' => [
            $base + ['type' => AssistantMessage::class, 'content' => 'Checking', 'contentAsBase64' => [], 'toolsCalls' => [$toolCall]],
            new AssistantMessage(new Text('Checking'), new ToolCall('call_1', 'weather', ['city' => 'Berlin'])),
        ];
        yield 'tool call' => [
            $base + ['type' => ToolCallMessage::class, 'content' => 'Sunny', 'contentAsBase64' => [], 'toolsCalls' => $toolCall + ['signature' => 'tool-sig'], 'parts' => []],
            new ToolCallMessage(new ToolCall('call_1', 'weather', ['city' => 'Berlin'], 'tool-sig'), new Text('Sunny')),
        ];
    }

    public function testItRejectsUnknownMessageType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown message type "Foo".');

        (new MessageNormalizer())->denormalize(['id' => Uuid::v7()->toRfc4122(), 'type' => 'Foo'], MessageInterface::class);
    }

    public function testItRejectsUnknownContentType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported content type "Foo".');

        (new MessageNormalizer())->denormalize([
            'id' => Uuid::v7()->toRfc4122(),
            'type' => UserMessage::class,
            'parts' => [['type' => 'Foo']],
        ], MessageInterface::class);
    }
}
