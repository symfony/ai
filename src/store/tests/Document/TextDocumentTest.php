<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

#[CoversClass(TextDocument::class)]
final class TextDocumentTest extends TestCase
{
    #[TestDox('Creates document with valid content and metadata')]
    public function testConstructorWithValidContent()
    {
        $id = Uuid::v4();
        $content = 'This is valid content';
        $metadata = new Metadata(['title' => 'Test Document']);

        $document = new TextDocument($id, $content, $metadata);

        $this->assertSame($id, $document->id);
        $this->assertSame($content, $document->content);
        $this->assertSame($metadata, $document->metadata);
    }

    #[TestDox('Creates document with default empty metadata when not provided')]
    public function testConstructorWithDefaultMetadata()
    {
        $id = Uuid::v4();
        $content = 'This is valid content';

        $document = new TextDocument($id, $content);

        $this->assertSame($id, $document->id);
        $this->assertSame($content, $document->content);
        $this->assertInstanceOf(Metadata::class, $document->metadata);
        $this->assertCount(0, $document->metadata);
    }

    #[TestWith([''])]
    #[TestWith([' '])]
    #[TestWith(['     '])]
    #[TestWith(["\t\t\t"])]
    #[TestWith(["\n\n\n"])]
    #[TestWith([" \t \n \r "])]
    #[TestWith(["\r\r\r"])]
    #[TestDox('Throws exception for invalid content: $content')]
    public function testConstructorThrowsExceptionForInvalidContent(string $content)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The content shall not be an empty string.');

        new TextDocument(Uuid::v4(), $content);
    }

    #[TestWith(['Hello, World!'])]
    #[TestWith(['   Leading whitespace'])]
    #[TestWith(['Trailing whitespace   '])]
    #[TestWith(["Line 1\nLine 2\nLine 3"])]
    #[TestWith(["  Text with\t\ttabs and\n\nnewlines  "])]
    #[TestWith(['a'])]
    #[TestWith(['123456789'])]
    #[TestWith(['!@#$%^&*()_+-=[]{}|;:,.<>?'])]
    #[TestWith(['Hello 世界 🌍'])]
    #[TestWith(['{"key": "value", "number": 42}'])]
    #[TestWith(['<html><body><p>Hello World</p></body></html>'])]
    #[TestWith(['# Heading\n\nThis is **bold** and this is *italic*.'])]
    #[TestDox('Accepts valid content')]
    public function testConstructorAcceptsValidContent(string $content)
    {
        $id = Uuid::v4();

        $document = new TextDocument($id, $content);

        $this->assertSame($id, $document->id);
        $this->assertSame($content, $document->content);
    }

    #[TestDox('Accepts very long text content')]
    public function testConstructorAcceptsVeryLongContent()
    {
        $id = Uuid::v4();
        $content = str_repeat('Lorem ipsum dolor sit amet, ', 1000);

        $document = new TextDocument($id, $content);

        $this->assertSame($id, $document->id);
        $this->assertSame($content, $document->content);
    }

    #[TestDox('Properties are publicly accessible and readonly')]
    public function testReadonlyProperties()
    {
        $id = Uuid::v4();
        $content = 'Test content';
        $metadata = new Metadata(['key' => 'value']);

        $document = new TextDocument($id, $content, $metadata);

        // Test that properties are publicly accessible
        $this->assertSame($id, $document->id);
        $this->assertSame($content, $document->content);
        $this->assertSame($metadata, $document->metadata);
    }

    #[TestDox('Metadata contents can be modified even though the property is readonly')]
    public function testMetadataCanBeModified()
    {
        $id = Uuid::v4();
        $content = 'Test content';
        $metadata = new Metadata();

        $document = new TextDocument($id, $content, $metadata);

        // Metadata is readonly but its contents can be modified (ArrayObject behavior)
        $metadata['key'] = 'value';
        $metadata->setSource('test.txt');

        $this->assertSame('value', $document->metadata['key']);
        $this->assertSame('test.txt', $document->metadata->getSource());
    }

    #[DataProvider('uuidVersionProvider')]
    #[TestDox('Accepts UUID version $version')]
    public function testDifferentUuidVersions(string $version, Uuid $uuid)
    {
        $content = 'Test content';

        $document = new TextDocument($uuid, $content);

        $this->assertSame($uuid, $document->id);
        $this->assertSame($content, $document->content);
    }

    /**
     * @return \Iterator<string, array{version: string, uuid: Uuid}>
     */
    public static function uuidVersionProvider(): \Iterator
    {
        yield 'UUID v4' => ['version' => '4', 'uuid' => Uuid::v4()];
        yield 'UUID v6' => ['version' => '6', 'uuid' => Uuid::v6()];
        yield 'UUID v7' => ['version' => '7', 'uuid' => Uuid::v7()];
    }

    #[TestDox('Handles complex nested metadata with special keys')]
    public function testDocumentWithComplexMetadata()
    {
        $id = Uuid::v4();
        $content = 'Document content';
        $metadata = new Metadata([
            'title' => 'Test Document',
            'author' => 'John Doe',
            'tags' => ['test', 'document', 'example'],
            'created_at' => '2024-01-01',
            'version' => 1.0,
            'nested' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ]);
        $metadata->setParentId('parent-123');
        $metadata->setText('Additional text');
        $metadata->setSource('source.pdf');

        $document = new TextDocument($id, $content, $metadata);

        $expected = [
            'title' => 'Test Document',
            'author' => 'John Doe',
            'tags' => ['test', 'document', 'example'],
            'created_at' => '2024-01-01',
            'version' => 1.0,
            'nested' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
            '_parent_id' => 'parent-123',
            '_text' => 'Additional text',
            '_source' => 'source.pdf',
        ];

        $this->assertSame($expected, $document->metadata->getArrayCopy());
    }

    #[TestDox('Multiple documents can share the same content with different IDs and metadata')]
    public function testMultipleDocumentsWithSameContent()
    {
        $content = 'Shared content';
        $metadata1 = new Metadata(['source' => 'doc1.txt']);
        $metadata2 = new Metadata(['source' => 'doc2.txt']);

        $document1 = new TextDocument(Uuid::v4(), $content, $metadata1);
        $document2 = new TextDocument(Uuid::v4(), $content, $metadata2);

        $this->assertSame($content, $document1->content);
        $this->assertSame($content, $document2->content);
        $this->assertNotSame($document1->id, $document2->id);
        $this->assertNotSame($document1->metadata, $document2->metadata);
    }

    #[TestDox('Documents can have the same ID but different content')]
    public function testDocumentWithSameIdButDifferentContent()
    {
        $id = Uuid::v4();

        $document1 = new TextDocument($id, 'Content 1');
        $document2 = new TextDocument($id, 'Content 2');

        $this->assertSame($id, $document1->id);
        $this->assertSame($id, $document2->id);
        $this->assertNotSame($document1->content, $document2->content);
    }

    #[TestDox('Content with whitespace is stored as-is without trimming')]
    public function testTrimBehaviorValidation()
    {
        // Content with whitespace that is not purely whitespace should be valid
        $id = Uuid::v4();
        $contentWithWhitespace = '  Valid content with spaces  ';

        $document = new TextDocument($id, $contentWithWhitespace);

        // The content is stored as-is, not trimmed
        $this->assertSame($contentWithWhitespace, $document->content);
    }

    #[TestDox('Exception message is correct for empty content')]
    public function testExceptionMessageIsCorrect()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The content shall not be an empty string.');

        new TextDocument(Uuid::v4(), '   ');
    }

    #[TestDox('withContent() creates new instance with updated content')]
    public function testWithContent()
    {
        $id = Uuid::v4();
        $originalContent = 'Original content';
        $newContent = 'Updated content';
        $metadata = new Metadata(['title' => 'Test Document']);

        $originalDocument = new TextDocument($id, $originalContent, $metadata);
        $updatedDocument = $originalDocument->withContent($newContent);

        // Original document is unchanged
        $this->assertSame($originalContent, $originalDocument->content);
        
        // New document has updated content but same ID and metadata
        $this->assertSame($newContent, $updatedDocument->content);
        $this->assertSame($id, $updatedDocument->id);
        $this->assertSame($metadata, $updatedDocument->metadata);
        
        // Different instances
        $this->assertNotSame($originalDocument, $updatedDocument);
    }

    #[TestDox('withContent() validates new content')]
    public function testWithContentValidatesContent()
    {
        $document = new TextDocument(Uuid::v4(), 'Valid content');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The content shall not be an empty string.');

        $document->withContent('   ');
    }
}
