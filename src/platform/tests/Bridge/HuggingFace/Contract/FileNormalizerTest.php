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
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\FileNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Model;

final class FileNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new FileNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new File('some content', 'image/jpeg'), context: [
            Contract::CONTEXT_MODEL => new Model('test-model'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a file'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new FileNormalizer();

        $expected = [
            File::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(File $file, array $expected)
    {
        $normalizer = new FileNormalizer();

        $normalized = $normalizer->normalize($file);

        $this->assertEquals($expected, $normalized);
    }

    public static function normalizeDataProvider(): iterable
    {
        yield 'image from file' => [
            File::fromFile(\dirname(__DIR__, 3).'/Fixtures/image.jpg'),
            [
                'headers' => ['Content-Type' => 'image/jpeg'],
                'body' => file_get_contents(\dirname(__DIR__, 3).'/Fixtures/image.jpg'),
            ],
        ];

        yield 'pdf document from file' => [
            File::fromFile(\dirname(__DIR__, 3).'/Fixtures/document.pdf'),
            [
                'headers' => ['Content-Type' => 'application/pdf'],
                'body' => file_get_contents(\dirname(__DIR__, 3).'/Fixtures/document.pdf'),
            ],
        ];

        yield 'audio from file' => [
            File::fromFile(\dirname(__DIR__, 3).'/Fixtures/audio.mp3'),
            [
                'headers' => ['Content-Type' => 'audio/mpeg'],
                'body' => file_get_contents(\dirname(__DIR__, 3).'/Fixtures/audio.mp3'),
            ],
        ];

        yield 'text file from content' => [
            new File('Hello World', 'text/plain'),
            [
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'Hello World',
            ],
        ];
    }
}
