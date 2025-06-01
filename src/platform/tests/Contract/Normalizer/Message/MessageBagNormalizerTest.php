<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\Message\MessageBagNormalizer;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[CoversClass(MessageBagNormalizer::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(Text::class)]
#[UsesClass(GPT::class)]
#[UsesClass(Model::class)]
final class MessageBagNormalizerTest extends TestCase
{
    private MessageBagNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MessageBagNormalizer();
    }

    #[Test]
    public function supportsNormalization(): void
    {
        $messageBag = $this->createMock(MessageBagInterface::class);

        self::assertTrue($this->normalizer->supportsNormalization($messageBag));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        self::assertSame([MessageBagInterface::class => true], $this->normalizer->getSupportedTypes(null));
    }

    #[Test]
    public function normalizeWithoutModel(): void
    {
        $messages = [
            new SystemMessage('You are a helpful assistant'),
            new UserMessage(new Text('Hello')),
        ];

        $messageBag = new MessageBag(...$messages);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects(self::once())
            ->method('normalize')
            ->with($messages, null, [])
            ->willReturn([
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
                ['role' => 'user', 'content' => 'Hello'],
            ]);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ];

        self::assertSame($expected, $this->normalizer->normalize($messageBag));
    }

    #[Test]
    public function normalizeWithModel(): void
    {
        $messages = [
            new SystemMessage('You are a helpful assistant'),
            new UserMessage(new Text('Hello')),
        ];

        $messageBag = new MessageBag(...$messages);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects(self::once())
            ->method('normalize')
            ->with($messages, null, [Contract::CONTEXT_MODEL => new GPT()])
            ->willReturn([
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
                ['role' => 'user', 'content' => 'Hello'],
            ]);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'model' => 'gpt-4o',
        ];

        self::assertSame($expected, $this->normalizer->normalize($messageBag, context: [
            Contract::CONTEXT_MODEL => new GPT(),
        ]));
    }
}
