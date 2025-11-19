<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\Message\UserMessageNormalizer;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MessageBagNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new MessageBagNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new MessageBag(), context: [
            Contract::CONTEXT_MODEL => new Model('test-model'),
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

        $userMessageNormalizer = new UserMessageNormalizer();

        $mockNormalizer = $this->createMock(NormalizerInterface::class);
        $mockNormalizer->method('normalize')
            ->willReturnCallback(function ($messages) use ($userMessageNormalizer): array {
                $result = [];
                foreach ($messages as $message) {
                    if ($message instanceof UserMessage) {
                        $result[] = $userMessageNormalizer->normalize($message);
                    }
                }

                return $result;
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
        yield 'simple text message' => [
            new MessageBag(Message::ofUser('Hello, how are you?')),
            [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Hello, how are you?',
                        ],
                    ],
                ],
            ],
        ];

        yield 'multiple messages' => [
            new MessageBag(
                Message::ofUser('What is the capital of France?'),
                new UserMessage(new Text('Please provide a detailed answer.'))
            ),
            [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'What is the capital of France?',
                        ],
                        [
                            'role' => 'user',
                            'content' => 'Please provide a detailed answer.',
                        ],
                    ],
                ],
            ],
        ];

        yield 'empty message bag' => [
            new MessageBag(),
            [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'messages' => [],
                ],
            ],
        ];
    }
}
