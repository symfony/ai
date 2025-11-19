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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Metadata;

final class MetadataTest extends TestCase
{
    public function testMetadataExtendsArrayObject()
    {
        $metadata = new Metadata();

        $this->assertInstanceOf(\ArrayObject::class, $metadata);
    }

    public function testMetadataCanBeInitializedWithData()
    {
        $data = ['title' => 'Test Document', 'category' => 'test'];
        $metadata = new Metadata($data);

        $this->assertSame('Test Document', $metadata['title']);
        $this->assertSame('test', $metadata['category']);
        $this->assertSame($data, $metadata->getArrayCopy());
    }

    public function testConstants()
    {
        $this->assertSame('_parent_id', Metadata::KEY_PARENT_ID);
        $this->assertSame('_text', Metadata::KEY_TEXT);
        $this->assertSame('_source', Metadata::KEY_SOURCE);
    }

    #[DataProvider('parentIdProvider')]
    public function testParentIdMethods(int|string|null $parentId)
    {
        $metadata = new Metadata();

        $this->assertFalse($metadata->hasParentId());
        $this->assertNull($metadata->getParentId());

        $metadata->setParentId($parentId);

        $this->assertTrue($metadata->hasParentId());
        $this->assertSame($parentId, $metadata->getParentId());
    }

    /**
     * @return \Iterator<string, array{parentId: int|string|null}>
     */
    public static function parentIdProvider(): \Iterator
    {
        yield 'integer parent id' => [
            'parentId' => 123,
        ];

        yield 'string parent id' => [
            'parentId' => 'parent-123',
        ];
    }

    #[DataProvider('textProvider')]
    public function testTextMethods(?string $text)
    {
        $metadata = new Metadata();

        $this->assertFalse($metadata->hasText());
        $this->assertNull($metadata->getText());

        $metadata->setText($text);

        $this->assertTrue($metadata->hasText());
        $this->assertSame($text, $metadata->getText());
    }

    /**
     * @return \Iterator<string, array{text: string|null}>
     */
    public static function textProvider(): \Iterator
    {
        yield 'string text' => [
            'text' => 'This is some text content',
        ];

        yield 'empty string text' => [
            'text' => '',
        ];
    }

    #[DataProvider('sourceProvider')]
    public function testSourceMethods(?string $source)
    {
        $metadata = new Metadata();

        $this->assertFalse($metadata->hasSource());
        $this->assertNull($metadata->getSource());

        $metadata->setSource($source);

        $this->assertTrue($metadata->hasSource());
        $this->assertSame($source, $metadata->getSource());
    }

    /**
     * @return \Iterator<string, array{source: string|null}>
     */
    public static function sourceProvider(): \Iterator
    {
        yield 'string source' => [
            'source' => 'document.pdf',
        ];

        yield 'empty string source' => [
            'source' => '',
        ];
    }

    public function testMetadataInitializedWithSpecialKeys()
    {
        $data = [
            Metadata::KEY_PARENT_ID => 'parent-123',
            Metadata::KEY_TEXT => 'This is the text content',
            Metadata::KEY_SOURCE => 'document.pdf',
            'title' => 'Test Document',
        ];

        $metadata = new Metadata($data);

        $this->assertTrue($metadata->hasParentId());
        $this->assertSame('parent-123', $metadata->getParentId());

        $this->assertTrue($metadata->hasText());
        $this->assertSame('This is the text content', $metadata->getText());

        $this->assertTrue($metadata->hasSource());
        $this->assertSame('document.pdf', $metadata->getSource());

        $this->assertSame('Test Document', $metadata['title']);
    }

    public function testArrayObjectBehavior()
    {
        $metadata = new Metadata();

        $metadata['title'] = 'Test Document';
        $metadata['category'] = 'test';

        $this->assertSame('Test Document', $metadata['title']);
        $this->assertSame('test', $metadata['category']);

        $this->assertTrue(isset($metadata['title']));
        $this->assertFalse(isset($metadata['nonexistent']));

        unset($metadata['category']);
        $this->assertFalse(isset($metadata['category']));

        $this->assertCount(1, $metadata);
    }

    public function testIteratorBehavior()
    {
        $data = ['title' => 'Test Document', 'category' => 'test', 'author' => 'John Doe'];
        $metadata = new Metadata($data);

        $iteratedData = [];
        foreach ($metadata as $key => $value) {
            $iteratedData[$key] = $value;
        }

        $this->assertSame($data, $iteratedData);
    }

    public function testGettersReturnNullForMissingKeys()
    {
        $metadata = new Metadata();

        $this->assertNull($metadata->getParentId());
        $this->assertNull($metadata->getText());
        $this->assertNull($metadata->getSource());
    }

    public function testHasMethodsReturnFalseForMissingKeys()
    {
        $metadata = new Metadata();

        $this->assertFalse($metadata->hasParentId());
        $this->assertFalse($metadata->hasText());
        $this->assertFalse($metadata->hasSource());
    }

    public function testOverwritingSpecialKeys()
    {
        $metadata = new Metadata();

        $metadata->setParentId('parent-1');
        $metadata->setText('initial text');
        $metadata->setSource('initial.pdf');

        $metadata->setParentId('parent-2');
        $metadata->setText('updated text');
        $metadata->setSource('updated.pdf');

        $this->assertSame('parent-2', $metadata->getParentId());
        $this->assertSame('updated text', $metadata->getText());
        $this->assertSame('updated.pdf', $metadata->getSource());
    }
}
