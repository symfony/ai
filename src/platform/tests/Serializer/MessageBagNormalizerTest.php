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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

final class MessageBagNormalizerTest extends TestCase
{
    public function testRoundTripKeepsIdMetadataAndMessages()
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin']);
        $messages = new MessageBag(
            Message::forSystem('You are a helpful assistant.'),
            Message::ofUser('How is the weather?'),
            new AssistantMessage(new Text('Checking'), $toolCall),
            Message::ofToolCall($toolCall, 'Sunny'),
            Message::ofAssistant('It is sunny.'),
        );
        $messages->getMetadata()->add('sticky_agent', 'weather');
        $normalizer = new MessageBagNormalizer();
        $serializer = new Serializer([$normalizer], [new JsonEncoder()]);

        $restored = $serializer->deserialize($serializer->serialize($messages, 'json'), MessageBag::class, 'json');

        $this->assertTrue($messages->getId()->equals($restored->getId()));
        $this->assertSame('weather', $restored->getMetadata()->get('sticky_agent'));
        $this->assertSame($normalizer->normalize($messages), $normalizer->normalize($restored));
    }

    public function testItRejectsInvalidData()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The normalized message bag must be an array with an "id" and a "messages" key.');

        (new MessageBagNormalizer())->denormalize(['messages' => []], MessageBag::class);
    }
}
