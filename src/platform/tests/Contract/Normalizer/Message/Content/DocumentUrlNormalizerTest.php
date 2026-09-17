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
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\DocumentUrlNormalizer;
use Symfony\AI\Platform\Message\Content\DocumentUrl;

final class DocumentUrlNormalizerTest extends TestCase
{
    private DocumentUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DocumentUrlNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new DocumentUrl('https://example.com/doc.pdf')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([DocumentUrl::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $documentUrl = new DocumentUrl('https://example.com/doc.pdf');

        $expected = [
            'type' => 'document_url',
            'document_url' => ['url' => 'https://example.com/doc.pdf'],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($documentUrl));
    }
}
