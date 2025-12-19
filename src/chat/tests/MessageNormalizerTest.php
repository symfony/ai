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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
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
}
