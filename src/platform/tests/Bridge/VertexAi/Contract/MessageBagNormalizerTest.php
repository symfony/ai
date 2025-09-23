<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Medium]
#[CoversClass(MessageBagNormalizer::class)]
#[CoversClass(UserMessageNormalizer::class)]
#[CoversClass(AssistantMessageNormalizer::class)]
#[UsesClass(BaseModel::class)]
#[UsesClass(Model::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(AssistantMessage::class)]
final class MessageBagNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new MessageBagNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new MessageBag(), context: [
            Contract::CONTEXT_MODEL => new Model(Model::GEMINI_2_5_PRO),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a message bag'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new MessageBagNormalizer();

        $expected = [
            MessageBag::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('provideMessageBagData')]
    public function testNormalize(MessageBag $bag, array $expected)
    {
        $normalizer = new MessageBagNormalizer();

        // Set up the inner normalizers
        $userMessageNormalizer = new UserMessageNormalizer();
        $assistantMessageNormalizer = new AssistantMessageNormalizer();

        // Mock a normalizer that delegates to the appropriate concrete normalizer
        $mockNormalizer = $this->createMock(NormalizerInterface::class);
        $mockNormalizer->method('normalize')
            ->willReturnCallback(function ($message) use ($userMessageNormalizer, $assistantMessageNormalizer): ?array {
                if ($message instanceof UserMessage) {
                    return $userMessageNormalizer->normalize($message);
                }
                if ($message instanceof AssistantMessage) {
                    return $assistantMessageNormalizer->normalize($message);
                }

                return null;
            });

        $normalizer->setNormalizer($mockNormalizer);

        $normalized = $normalizer->normalize($bag);

        $this->assertEquals($expected, $normalized);
    }

    /**
     * @return iterable<array{0: MessageBag, 1: array}>
     */
    public static function provideMessageBagData(): iterable
    {
        yield 'simple text' => [
            new MessageBag(Message::ofUser('Write a story about a magic backpack.')),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Write a story about a magic backpack.']]],
                ],
            ],
        ];

        yield 'text with image' => [
            new MessageBag(
                Message::ofUser('Tell me about this instrument', Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg'))
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [
                        ['text' => 'Tell me about this instrument'],
                        ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => base64_encode(file_get_contents(\dirname(__DIR__, 6).'/fixtures/image.jpg'))]],
                    ]],
                ],
            ],
        ];

        yield 'with assistant message' => [
            new MessageBag(
                Message::ofUser('Hello'),
                Message::ofAssistant('Great to meet you. What would you like to know?'),
                Message::ofUser('I have two dogs in my house. How many paws are in my house?'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Hello']]],
                    ['role' => 'model', 'parts' => [['text' => 'Great to meet you. What would you like to know?']]],
                    ['role' => 'user', 'parts' => [['text' => 'I have two dogs in my house. How many paws are in my house?']]],
                ],
            ],
        ];

        yield 'with system messages' => [
            new MessageBag(
                Message::forSystem('You are a cat. Your name is Neko.'),
                Message::ofUser('Hello there'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Hello there']]],
                ],
                'systemInstruction' => [
                    'parts' => [['text' => 'You are a cat. Your name is Neko.']],
                ],
            ],
        ];
    }
}
