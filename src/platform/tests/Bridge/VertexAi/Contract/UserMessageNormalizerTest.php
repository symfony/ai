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
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;

#[Small]
#[CoversClass(UserMessageNormalizer::class)]
#[UsesClass(Model::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(Text::class)]
#[UsesClass(File::class)]
#[UsesClass(Image::class)]
#[UsesClass(Document::class)]
#[UsesClass(Audio::class)]
final class UserMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new UserMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new UserMessage(new Text('Hello')), context: [
            Contract::CONTEXT_MODEL => new Model(),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a user message'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new UserMessageNormalizer();

        $this->assertSame([UserMessage::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalizeTextContent()
    {
        $normalizer = new UserMessageNormalizer();
        $message = new UserMessage(new Text('Write a story about a magic backpack.'));

        $normalized = $normalizer->normalize($message);

        $this->assertSame([['text' => 'Write a story about a magic backpack.']], $normalized);
    }

    #[DataProvider('binaryContentProvider')]
    public function testNormalizeBinaryContent(File $content, string $expectedMimeType, string $expectedPrefix)
    {
        $normalizer = new UserMessageNormalizer();
        $message = new UserMessage(new Text('Tell me about this instrument'), $content);

        $normalized = $normalizer->normalize($message);

        $this->assertCount(2, $normalized);
        $this->assertSame(['text' => 'Tell me about this instrument'], $normalized[0]);
        $this->assertArrayHasKey('inlineData', $normalized[1]);
        $this->assertSame($expectedMimeType, $normalized[1]['inlineData']['mimeType']);
        $this->assertNotEmpty($normalized[1]['inlineData']['data']);

        $this->assertStringStartsWith($expectedPrefix, $normalized[1]['inlineData']['data']);
    }

    /**
     * @return iterable<string, array{0: File, 1: string, 2: string}>
     */
    public static function binaryContentProvider(): iterable
    {
        yield 'image' => [Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg'), 'image/jpeg', '/9j/'];
        yield 'document' => [Document::fromFile(\dirname(__DIR__, 6).'/fixtures/document.pdf'), 'application/pdf', 'JVBE'];
        yield 'audio' => [Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'), 'audio/mpeg', 'SUQz'];
    }
}
