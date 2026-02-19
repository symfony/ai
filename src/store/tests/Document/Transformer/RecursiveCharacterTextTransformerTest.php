<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Transformer;

use Symfony\AI\Store\Document\Transformer\RecursiveCharacterTextTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Uid\Uuid;

#[CoversClass(RecursiveCharacterTextTransformer::class)]
final class RecursiveCharacterTextTransformerTest extends TestCase
{
    #[Test]
    #[DataProvider('provideDocumentsContents')]
    public function it_works(array $inputDocumentsText, array $options, array $expectedSplittedTexts): void
    {
        $transformer = new RecursiveCharacterTextTransformer(chunkSize: $options['chunk_size'], separators: $options['separators']);
        $documents = \array_map(
            fn(string $text): TextDocument => new TextDocument(
                id: Uuid::v4(),
                content: $text,
            ),
            $inputDocumentsText
        );
        $transformedDocuments = \iterator_to_array($transformer->transform($documents));
        foreach($expectedSplittedTexts as $index => $expectedText) {
            $this->assertSame($expectedText, $transformedDocuments[$index]->getContent());
        }
    }

    public static function provideDocumentsContents(): \Generator
    {
        yield 'respects chunk size' => [
            [
              'Hello,great'
            ],
            ['chunk_size' => 1, 'separators' => [',']],
            [
              'H',
              'e',
              'l',
              'l',
              'o',
              'g',
              'r',
              'e',
              'a',
              't',
            ],
        ];
        yield 'splits by separators' => [
            [
              'Hel-lo,wow,great'
            ],
            ['chunk_size' => 3, 'separators' => [',', '-']],
            [
              'Hel',
              'lo',
              'wow',
              'gre',
              'at'
            ],
        ];
        yield 'single_document_1_splits' => [
            [
                'Hello this is great nice to meet you here.\nIt was a pleasure to meet you.',
            ],
            ['chunk_size' => 75,'separators' => ['\n']],
            [
                'Hello this is great nice to meet you here.It was a pleasure to meet you.',
            ],
        ];

        yield 'single_document_2_splits' => [
            [
                'Hello this is great nice to meet you here.\nIt was a pleasure to meet you.',
            ],
            ['chunk_size' => 45,'separators' => ['\n']],
            [
                'Hello this is great nice to meet you here.',
                'It was a pleasure to meet you.',
            ],
        ];


    }
}
