<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Message\Content;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\FileNormalizer;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Video;

final class FileNormalizerTest extends TestCase
{
    private FileNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new FileNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new File('data', 'application/pdf')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([File::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $file = new File('data', 'application/pdf');

        $expected = [
            'type' => 'file',
            'file' => [
                'data' => base64_encode('data'),
                'format' => 'application/pdf',
            ],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($file));
    }

    public function testNormalizeHandlesVideoAndDocumentSubclasses()
    {
        $video = new Video('data', 'video/mp4');
        $document = new Document('data', 'application/pdf');

        $expected = [
            'type' => 'file',
            'file' => [
                'data' => base64_encode('data'),
                'format' => 'video/mp4',
            ],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($video));

        $expectedDocument = [
            'type' => 'file',
            'file' => [
                'data' => base64_encode('data'),
                'format' => 'application/pdf',
            ],
        ];

        $this->assertSame($expectedDocument, $this->normalizer->normalize($document));
    }
}
