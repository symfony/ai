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
use Symfony\AI\Platform\Bridge\Mistral\Contract\DocumentNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Document;

final class DocumentNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new DocumentNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Document('some content', 'application/pdf'), context: [
            Contract::CONTEXT_MODEL => new Mistral('mistral-large-latest'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a document'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new DocumentNormalizer();

        $expected = [
            Document::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(Document $file, array $expected)
    {
        $normalizer = new DocumentNormalizer();

        $normalized = $normalizer->normalize($file);

        $this->assertEquals($expected, $normalized);
    }

    public static function normalizeDataProvider(): iterable
    {
        yield 'document from file' => [
            Document::fromFile(\dirname(__DIR__, 3).'/Fixtures/document.pdf'),
            [
                'type' => 'document_url',
                'document_name' => 'document.pdf',
                'document_url' => 'data:application/pdf;base64,'.base64_encode(file_get_contents(\dirname(__DIR__, 3).'/Fixtures/document.pdf')),
            ],
        ];
    }
}
