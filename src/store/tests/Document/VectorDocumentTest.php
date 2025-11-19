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
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class VectorDocumentTest extends TestCase
{
    #[TestDox('Creates document with required parameters only')]
    public function testConstructorWithRequiredParameters()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument($id, $vector);

        $this->assertSame($id, $document->id);
        $this->assertSame($vector, $document->vector);
        $this->assertInstanceOf(Metadata::class, $document->metadata);
        $this->assertCount(0, $document->metadata);
        $this->assertNull($document->score);
    }

    #[TestDox('Creates document with metadata')]
    public function testConstructorWithMetadata()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata(['source' => 'test.txt', 'author' => 'John Doe']);

        $document = new VectorDocument($id, $vector, $metadata);

        $this->assertSame($id, $document->id);
        $this->assertSame($vector, $document->vector);
        $this->assertSame($metadata, $document->metadata);
        $this->assertSame(['source' => 'test.txt', 'author' => 'John Doe'], $document->metadata->getArrayCopy());
    }

    #[TestDox('Creates document with all parameters including score')]
    public function testConstructorWithAllParameters()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata(['title' => 'Test Document']);
        $score = 0.95;

        $document = new VectorDocument($id, $vector, $metadata, $score);

        $this->assertSame($id, $document->id);
        $this->assertSame($vector, $document->vector);
        $this->assertSame($metadata, $document->metadata);
        $this->assertSame($score, $document->score);
    }

    #[TestWith([null])]
    #[TestWith([0.0])]
    #[TestWith([0.75])]
    #[TestWith([-0.25])]
    #[TestWith([1.0])]
    #[TestWith([0.000001])]
    #[TestWith([999999.99])]
    #[TestDox('Handles different score values: $score')]
    public function testConstructorWithDifferentScores(?float $score)
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument($id, $vector, new Metadata(), $score);

        $this->assertSame($score, $document->score);
    }

    #[TestDox('Handles metadata with special keys')]
    public function testMetadataWithSpecialKeys()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata([
            Metadata::KEY_PARENT_ID => 'parent-123',
            Metadata::KEY_TEXT => 'Additional text information',
            Metadata::KEY_SOURCE => 'document.pdf',
            'custom_field' => 'custom_value',
        ]);

        $document = new VectorDocument($id, $vector, $metadata);

        $this->assertSame($metadata, $document->metadata);
        $this->assertTrue($document->metadata->hasParentId());
        $this->assertSame('parent-123', $document->metadata->getParentId());
        $this->assertTrue($document->metadata->hasText());
        $this->assertSame('Additional text information', $document->metadata->getText());
        $this->assertTrue($document->metadata->hasSource());
        $this->assertSame('document.pdf', $document->metadata->getSource());
        $this->assertSame('custom_value', $document->metadata['custom_field']);
    }

    #[DataProvider('uuidProvider')]
    #[TestDox('Accepts different UUID versions')]
    public function testWithDifferentUuidVersions(Uuid $uuid)
    {
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument($uuid, $vector);

        $this->assertSame($uuid, $document->id);
    }

    /**
     * @return \Iterator<string, array{uuid: Uuid}>
     */
    public static function uuidProvider(): \Iterator
    {
        yield 'UUID v1' => ['uuid' => Uuid::v1()];
        yield 'UUID v4' => ['uuid' => Uuid::v4()];
        yield 'UUID v6' => ['uuid' => Uuid::v6()];
        yield 'UUID v7' => ['uuid' => Uuid::v7()];
    }

    #[TestDox('Handles complex nested metadata structures')]
    public function testWithComplexMetadata()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata([
            'title' => 'Complex Document',
            'tags' => ['ai', 'ml', 'vectors'],
            'nested' => [
                'level1' => [
                    'level2' => 'deep value',
                ],
            ],
            'timestamp' => 1234567890,
            'active' => true,
        ]);

        $document = new VectorDocument($id, $vector, $metadata);

        $this->assertSame($metadata, $document->metadata);
        $this->assertSame('Complex Document', $document->metadata['title']);
        $this->assertSame(['ai', 'ml', 'vectors'], $document->metadata['tags']);
        $this->assertSame(['level1' => ['level2' => 'deep value']], $document->metadata['nested']);
        $this->assertSame(1234567890, $document->metadata['timestamp']);
        $this->assertTrue($document->metadata['active']);
    }

    #[TestDox('Verifies vector interface methods are accessible')]
    public function testVectorInterfaceInteraction()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument($id, $vector);

        $this->assertSame([0.1, 0.2, 0.3], $document->vector->getData());
        $this->assertSame(3, $document->vector->getDimensions());
    }

    #[TestDox('Multiple documents can share the same metadata instance')]
    public function testMultipleDocumentsWithSameMetadataInstance()
    {
        $metadata = new Metadata(['shared' => 'value']);
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);

        $document1 = new VectorDocument(Uuid::v4(), $vector1, $metadata);
        $document2 = new VectorDocument(Uuid::v4(), $vector2, $metadata);

        // Both documents share the same metadata instance
        $this->assertSame($metadata, $document1->metadata);
        $this->assertSame($metadata, $document2->metadata);
        $this->assertSame($document1->metadata, $document2->metadata);
    }

    #[TestDox('Documents with same values are equal but not identical')]
    public function testDocumentEquality()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata(['key' => 'value']);
        $score = 0.75;

        $document1 = new VectorDocument($id, $vector, $metadata, $score);
        $document2 = new VectorDocument($id, $vector, $metadata, $score);

        // Same values but different instances
        $this->assertNotSame($document1, $document2);

        // But properties should be the same
        $this->assertSame($document1->id, $document2->id);
        $this->assertSame($document1->vector, $document2->vector);
        $this->assertSame($document1->metadata, $document2->metadata);
        $this->assertSame($document1->score, $document2->score);
    }
}
