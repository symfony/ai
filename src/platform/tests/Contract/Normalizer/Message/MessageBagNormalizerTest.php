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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\Message\MessageBagNormalizer;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[CoversClass(MessageBagNormalizer::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(Text::class)]
#[UsesClass(Gpt::class)]
#[UsesClass(Model::class)]
final class MessageBagNormalizerTest extends TestCase
{
    private MessageBagNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MessageBagNormalizer();
    }

    public function testSupportsNormalization()
    {
        $messageBag = $this->createMock(MessageBag::class);

        $this->assertTrue($this->normalizer->supportsNormalization($messageBag));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([MessageBag::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeWithoutModel()
    {
        $messages = [
            new SystemMessage('You are a helpful assistant'),
            new UserMessage(new Text('Hello')),
        ];

        $messageBag = new MessageBag(...$messages);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
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

        $this->assertSame($expected, $this->normalizer->normalize($messageBag));
    }

    public function testNormalizeWithModel()
    {
        $messages = [
            new SystemMessage('You are a helpful assistant'),
            new UserMessage(new Text('Hello')),
        ];

        $messageBag = new MessageBag(...$messages);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($messages, null, [Contract::CONTEXT_MODEL => new Gpt(Gpt::GPT_4O)])
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

        $this->assertSame($expected, $this->normalizer->normalize($messageBag, context: [
            Contract::CONTEXT_MODEL => new Gpt(Gpt::GPT_4O),
        ]));
    }
}
