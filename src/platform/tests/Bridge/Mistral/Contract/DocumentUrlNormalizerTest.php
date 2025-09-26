<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Mistral\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Contract\DocumentUrlNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\DocumentUrl;

final class DocumentUrlNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new DocumentUrlNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new DocumentUrl('https://example.com/document.pdf'), context: [
            Contract::CONTEXT_MODEL => new Mistral('mistral-large-latest'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a document url'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new DocumentUrlNormalizer();

        $expected = [
            DocumentUrl::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(DocumentUrl $file, array $expected)
    {
        $normalizer = new DocumentUrlNormalizer();

        $normalized = $normalizer->normalize($file);

        $this->assertEquals($expected, $normalized);
    }

    public static function normalizeDataProvider(): iterable
    {
        yield 'document with url' => [
            new DocumentUrl('https://example.com/document.pdf'),
            [
                'type' => 'document_url',
                'document_url' => 'https://example.com/document.pdf',
            ],
        ];
    }
}
