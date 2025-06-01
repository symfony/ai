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
use Symfony\AI\Platform\Contract\Normalizer\Message\UserMessageNormalizer;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[CoversClass(UserMessageNormalizer::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(Text::class)]
#[UsesClass(ImageUrl::class)]
final class UserMessageNormalizerTest extends TestCase
{
    private UserMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UserMessageNormalizer();
    }

    #[Test]
    public function supportsNormalization(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new UserMessage(new Text('content'))));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        self::assertSame([UserMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    #[Test]
    public function normalizeWithSingleTextContent(): void
    {
        $textContent = new Text('Hello, how can you help me?');
        $message = new UserMessage($textContent);

        $expected = [
            'role' => 'user',
            'content' => 'Hello, how can you help me?',
        ];

        self::assertSame($expected, $this->normalizer->normalize($message));
    }

    #[Test]
    public function normalizeWithMixedContent(): void
    {
        $textContent = new Text('Please describe this image:');
        $imageContent = new ImageUrl('https://example.com/image.jpg');
        $message = new UserMessage($textContent, $imageContent);

        $expectedContent = [
            ['type' => 'text', 'text' => 'Please describe this image:'],
            ['type' => 'image', 'url' => 'https://example.com/image.jpg'],
        ];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects(self::once())
            ->method('normalize')
            ->with($message->content, null, [])
            ->willReturn($expectedContent);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'user',
            'content' => $expectedContent,
        ];

        self::assertSame($expected, $this->normalizer->normalize($message));
    }
}
