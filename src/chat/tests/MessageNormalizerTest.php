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
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Result\ToolCallNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageNormalizerTest extends TestCase
{
    public function testItIsConfigured()
    {
        $normalizer = new MessageNormalizer();

        $this->assertSame([
            MessageInterface::class => true,
        ], $normalizer->getSupportedTypes(''));

        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
        $this->assertTrue($normalizer->supportsNormalization(Message::ofUser()));

        $this->assertFalse($normalizer->supportsDenormalization('', \stdClass::class));
        $this->assertTrue($normalizer->supportsDenormalization('', MessageInterface::class));
    }

    public function testItCanNormalize()
    {
        $normalizer = new MessageNormalizer();

        $payload = $normalizer->normalize(Message::ofUser('Hello World'));

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('content', $payload);
        $this->assertArrayHasKey('contentAsBase64', $payload);
        $this->assertArrayHasKey('toolsCalls', $payload);
        $this->assertArrayHasKey('metadata', $payload);
        $this->assertArrayHasKey('addedAt', $payload);
    }

    public function testItCanDenormalize()
    {
        $uuid = Uuid::v7()->toRfc4122();
        $normalizer = new MessageNormalizer();

        $message = $normalizer->denormalize([
            'id' => $uuid,
            'type' => UserMessage::class,
            'content' => '',
            'contentAsBase64' => [
                [
                    'type' => Text::class,
                    'content' => 'What is the Symfony framework?',
                ],
            ],
            'toolsCalls' => [],
            'metadata' => [],
            'addedAt' => (new \DateTimeImmutable())->getTimestamp(),
        ], MessageInterface::class);

        $this->assertSame($uuid, $message->getId()->toRfc4122());
        $this->assertSame(Role::User, $message->getRole());
        $this->assertArrayHasKey('addedAt', $message->getMetadata()->all());
    }

    public function testItCanDenormalizeWithCustomIdentifier()
    {
        $normalizer = new MessageNormalizer();
        $message = Message::ofUser('Hello World');

        // Normalize with _id (like MongoDB)
        $payload = $normalizer->normalize($message, context: ['identifier' => '_id']);
        $this->assertArrayHasKey('_id', $payload);
        $this->assertArrayNotHasKey('id', $payload);

        $denormalized = $normalizer->denormalize($payload, MessageInterface::class, context: ['identifier' => '_id']);

        $this->assertSame($message->getId()->toRfc4122(), $denormalized->getId()->toRfc4122());
    }

    public function testItCanNormalizeAssistantMessageWithToolCalls()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new ToolCallNormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $message = new AssistantMessage('', [
            new ToolCall('call-1', 'get_weather', ['city' => 'Paris']),
        ]);

        $payload = $serializer->normalize($message);

        $this->assertSame(AssistantMessage::class, $payload['type']);
        $this->assertCount(1, $payload['toolsCalls']);
        $this->assertSame('call-1', $payload['toolsCalls'][0]['id']);
        $this->assertSame('function', $payload['toolsCalls'][0]['type']);
        $this->assertSame('get_weather', $payload['toolsCalls'][0]['function']['name']);
        $this->assertSame('{"city":"Paris"}', $payload['toolsCalls'][0]['function']['arguments']);
    }

    public function testItCanNormalizeAndDenormalizeAssistantMessageWithToolCalls()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new ToolCallNormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $message = new AssistantMessage('', [
            new ToolCall('call-1', 'get_weather', ['city' => 'Paris']),
            new ToolCall('call-2', 'get_time', []),
        ]);

        $payload = $serializer->normalize($message);
        $denormalized = $serializer->denormalize($payload, MessageInterface::class);

        $this->assertInstanceOf(AssistantMessage::class, $denormalized);
        $this->assertTrue($denormalized->hasToolCalls());

        $toolCalls = $denormalized->getToolCalls();
        $this->assertCount(2, $toolCalls);
        $this->assertSame('call-1', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());
        $this->assertSame('call-2', $toolCalls[1]->getId());
        $this->assertSame('get_time', $toolCalls[1]->getName());
        $this->assertSame([], $toolCalls[1]->getArguments());
    }

    public function testItCanNormalizeAndDenormalizeToolCallMessage()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new ToolCallNormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $message = new ToolCallMessage(
            new ToolCall('call-1', 'get_weather', ['city' => 'Paris']),
            'Sunny, 22°C',
        );

        $payload = $serializer->normalize($message);

        $this->assertSame(ToolCallMessage::class, $payload['type']);
        $this->assertSame('Sunny, 22°C', $payload['content']);
        $this->assertSame('call-1', $payload['toolsCalls']['id']);

        $denormalized = $serializer->denormalize($payload, MessageInterface::class);

        $this->assertInstanceOf(ToolCallMessage::class, $denormalized);
        $this->assertSame('Sunny, 22°C', $denormalized->getContent());
        $this->assertSame('call-1', $denormalized->getToolCall()->getId());
        $this->assertSame('get_weather', $denormalized->getToolCall()->getName());
        $this->assertSame(['city' => 'Paris'], $denormalized->getToolCall()->getArguments());
    }

    public function testItCanDenormalizeAssistantMessageWithFlatToolCalls()
    {
        // Flat payloads are produced when the outer serializer chain does
        // not include ToolCallNormalizer (as was the case before
        // ai-bundle's config was fixed). Restoring such payloads must
        // still work — otherwise conversations persisted before the fix
        // become unreadable.
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $uuid = Uuid::v7();
        $payload = [
            'id' => $uuid->toRfc4122(),
            'type' => AssistantMessage::class,
            'content' => '',
            'contentAsBase64' => [],
            'toolsCalls' => [
                [
                    'id' => 'call-1',
                    'name' => 'get_weather',
                    'arguments' => ['city' => 'Paris'],
                ],
            ],
            'metadata' => [],
            'addedAt' => 0,
        ];

        $denormalized = $serializer->denormalize($payload, MessageInterface::class);

        $this->assertInstanceOf(AssistantMessage::class, $denormalized);
        $toolCalls = $denormalized->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call-1', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());
    }

    public function testItCanDenormalizeToolCallMessageWithFlatToolCall()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $uuid = Uuid::v7();
        $payload = [
            'id' => $uuid->toRfc4122(),
            'type' => ToolCallMessage::class,
            'content' => 'Sunny, 22°C',
            'contentAsBase64' => [],
            'toolsCalls' => [
                'id' => 'call-1',
                'name' => 'get_weather',
                'arguments' => ['city' => 'Paris'],
            ],
            'metadata' => [],
            'addedAt' => 0,
        ];

        $denormalized = $serializer->denormalize($payload, MessageInterface::class);

        $this->assertInstanceOf(ToolCallMessage::class, $denormalized);
        $this->assertSame('call-1', $denormalized->getToolCall()->getId());
        $this->assertSame('get_weather', $denormalized->getToolCall()->getName());
        $this->assertSame(['city' => 'Paris'], $denormalized->getToolCall()->getArguments());
    }
}
