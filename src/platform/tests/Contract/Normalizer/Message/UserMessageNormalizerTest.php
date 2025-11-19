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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\UserMessageNormalizer;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class UserMessageNormalizerTest extends TestCase
{
    private UserMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UserMessageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new UserMessage(new Text('content'))));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([UserMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeWithSingleTextContent()
    {
        $textContent = new Text('Hello, how can you help me?');
        $message = new UserMessage($textContent);

        $expected = [
            'role' => 'user',
            'content' => 'Hello, how can you help me?',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithMixedContent()
    {
        $textContent = new Text('Please describe this image:');
        $imageContent = new ImageUrl('https://example.com/image.jpg');
        $message = new UserMessage($textContent, $imageContent);

        $expectedContent = [
            ['type' => 'text', 'text' => 'Please describe this image:'],
            ['type' => 'image', 'url' => 'https://example.com/image.jpg'],
        ];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($message->getContent(), null, [])
            ->willReturn($expectedContent);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'user',
            'content' => $expectedContent,
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }
}
