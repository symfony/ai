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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextTrimTransformer;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class TextTrimTransformerTest extends TestCase
{
    #[TestWith(['  text with spaces  ', 'text with spaces'])]
    #[TestWith(["\n\ntext with newlines\n\n", 'text with newlines'])]
    #[TestWith(["\t\ttext with tabs\t\t", 'text with tabs'])]
    #[TestWith(['  text  with  middle  spaces  ', 'text  with  middle  spaces'])]
    #[TestWith(['already trimmed', 'already trimmed'])]
    #[TestWith([' mixed   whitespace ', 'mixed   whitespace'])]
    #[TestWith(["\r\ncarriage return and newline\r\n", 'carriage return and newline'])]
    public function testTrim(string $input, string $expected)
    {
        $transformer = new TextTrimTransformer();
        $document = new TextDocument(Uuid::v4(), $input);

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame($expected, $result[0]->getContent());
    }

    public function testTrimHandlesOnlyWhitespace()
    {
        // Note: TextDocument doesn't allow empty content, so we can't test trimming to empty string
        // This test verifies that attempting to create a document with only whitespace throws an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The content shall not be an empty string.');

        new TextDocument(Uuid::v4(), '   ');
    }

    public function testTrimProcessesMultipleDocuments()
    {
        $transformer = new TextTrimTransformer();
        $documents = [
            new TextDocument(Uuid::v4(), '  first  '),
            new TextDocument(Uuid::v4(), '  second  '),
            new TextDocument(Uuid::v4(), '  third  '),
        ];

        $result = iterator_to_array($transformer->transform($documents));

        $this->assertCount(3, $result);
        $this->assertSame('first', $result[0]->getContent());
        $this->assertSame('second', $result[1]->getContent());
        $this->assertSame('third', $result[2]->getContent());
    }

    public function testTrimPreservesMetadata()
    {
        $transformer = new TextTrimTransformer();
        $metadata = new Metadata(['key' => 'value']);
        $document = new TextDocument(Uuid::v4(), '  text  ', $metadata);

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('text', $result[0]->getContent());
        $this->assertSame($metadata, $result[0]->getMetadata());
    }

    public function testTrimPreservesDocumentId()
    {
        $transformer = new TextTrimTransformer();
        $id = Uuid::v4();
        $document = new TextDocument($id, '  text  ');

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame($id, $result[0]->getId());
    }
}
