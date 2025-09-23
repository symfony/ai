<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Perplexity\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Perplexity\Contract\FileUrlNormalizer;
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\Message\MessageBagNormalizer;
use Symfony\AI\Platform\Message\Content\DocumentUrl;

#[Medium]
#[CoversClass(FileUrlNormalizer::class)]
#[CoversClass(MessageBagNormalizer::class)]
final class FileUrlNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new FileUrlNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new DocumentUrl(\dirname(__DIR__, 6).'/fixtures/not-a-document.pdf'), context: [
            Contract::CONTEXT_MODEL => new Perplexity(),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a document'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new FileUrlNormalizer();

        $expected = [
            DocumentUrl::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(DocumentUrl $document, array $expected)
    {
        $normalizer = new FileUrlNormalizer();

        $normalized = $normalizer->normalize($document);

        $this->assertEquals($expected, $normalized);
    }

    public static function normalizeDataProvider(): iterable
    {
        yield 'document from file url' => [
            new DocumentUrl(\dirname(__DIR__, 6).'/fixtures/document.pdf'),
            [
                'type' => 'file_url',
                'file_url' => [
                    'url' => \dirname(__DIR__, 6).'/fixtures/document.pdf',
                ],
            ],
        ];
    }
}
