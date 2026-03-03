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
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class VectorDocumentTest extends TestCase
{
    #[DataProvider('constructorIdDataProvider')]
    public function testConstructorIdSupportsManyTypes(int|string $id)
    {
        $document = new VectorDocument($id, new NullVector());

        $this->assertSame($id, $document->getId());
    }

    public static function constructorIdDataProvider(): iterable
    {
        yield 'int' => [1];
        yield 'string' => ['id'];
    }

    #[TestDox('Creates document with required parameters only')]
    public function testConstructorWithRequiredParameters()
    {
        $id = Uuid::v4()->toString();
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument($id, $vector);

        $this->assertSame($id, $document->getId());
        $this->assertSame($vector, $document->getVector());
        $this->assertInstanceOf(Metadata::class, $document->getMetadata());
        $this->assertCount(0, $document->getMetadata());
        $this->assertNull($document->getScore());
    }

    #[TestDox('Creates document with metadata')]
    public function testConstructorWithMetadata()
    {
        $id = Uuid::v4()->toString();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata(['source' => 'test.txt', 'author' => 'John Doe']);

        $document = new VectorDocument($id, $vector, $metadata);

        $this->assertSame($id, $document->getId());
        $this->assertSame($vector, $document->getVector());
        $this->assertSame($metadata, $document->getMetadata());
        $this->assertSame(['source' => 'test.txt', 'author' => 'John Doe'], $document->getMetadata()->getArrayCopy());
    }

    #[TestDox('Creates document with all parameters including score')]
    public function testConstructorWithAllParameters()
    {
        $id = Uuid::v4()->toString();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata(['title' => 'Test Document']);
        $score = 0.95;

        $document = new VectorDocument($id, $vector, $metadata, $score);

        $this->assertSame($id, $document->getId());
        $this->assertSame($vector, $document->getVector());
        $this->assertSame($metadata, $document->getMetadata());
        $this->assertSame($score, $document->getScore());
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
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument(Uuid::v4()->toString(), $vector, new Metadata(), $score);

        $this->assertSame($score, $document->getScore());
    }

    #[TestDox('Handles metadata with special keys')]
    public function testMetadataWithSpecialKeys()
    {
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata([
            Metadata::KEY_PARENT_ID => 'parent-123',
            Metadata::KEY_TEXT => 'Additional text information',
            Metadata::KEY_SOURCE => 'document.pdf',
            'custom_field' => 'custom_value',
        ]);

        $document = new VectorDocument(Uuid::v4()->toString(), $vector, $metadata);

        $this->assertSame($metadata, $document->getMetadata());
        $this->assertTrue($document->getMetadata()->hasParentId());
        $this->assertSame('parent-123', $document->getMetadata()->getParentId());
        $this->assertTrue($document->getMetadata()->hasText());
        $this->assertSame('Additional text information', $document->getMetadata()->getText());
        $this->assertTrue($document->getMetadata()->hasSource());
        $this->assertSame('document.pdf', $document->getMetadata()->getSource());
        $this->assertSame('custom_value', $document->getMetadata()['custom_field']);
    }

    #[TestDox('Handles complex nested metadata structures')]
    public function testWithComplexMetadata()
    {
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

        $document = new VectorDocument(Uuid::v4()->toString(), $vector, $metadata);

        $this->assertSame($metadata, $document->getMetadata());
        $this->assertSame('Complex Document', $document->getMetadata()['title']);
        $this->assertSame(['ai', 'ml', 'vectors'], $document->getMetadata()['tags']);
        $this->assertSame(['level1' => ['level2' => 'deep value']], $document->getMetadata()['nested']);
        $this->assertSame(1234567890, $document->getMetadata()['timestamp']);
        $this->assertTrue($document->getMetadata()['active']);
    }

    #[TestDox('Verifies vector interface methods are accessible')]
    public function testVectorInterfaceInteraction()
    {
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument(Uuid::v4()->toString(), $vector);

        $this->assertSame([0.1, 0.2, 0.3], $document->getVector()->getData());
        $this->assertSame(3, $document->getVector()->getDimensions());
    }

    #[TestDox('Multiple documents can share the same metadata instance')]
    public function testMultipleDocumentsWithSameMetadataInstance()
    {
        $metadata = new Metadata(['shared' => 'value']);
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);

        $document1 = new VectorDocument(Uuid::v4()->toString(), $vector1, $metadata);
        $document2 = new VectorDocument(Uuid::v4()->toString(), $vector2, $metadata);

        // Both documents share the same metadata instance
        $this->assertSame($metadata, $document1->getMetadata());
        $this->assertSame($metadata, $document2->getMetadata());
        $this->assertSame($document1->getMetadata(), $document2->getMetadata());
    }

    #[TestDox('Documents with same values are equal but not identical')]
    public function testDocumentEquality()
    {
        $id = Uuid::v4()->toString();
        $vector = new Vector([0.1, 0.2, 0.3]);
        $metadata = new Metadata(['key' => 'value']);
        $score = 0.75;

        $document1 = new VectorDocument($id, $vector, $metadata, $score);
        $document2 = new VectorDocument($id, $vector, $metadata, $score);

        // Same values but different instances
        $this->assertNotSame($document1, $document2);

        // But properties should be the same
        $this->assertSame($document1->getId(), $document2->getId());
        $this->assertSame($document1->getVector(), $document2->getVector());
        $this->assertSame($document1->getMetadata(), $document2->getMetadata());
        $this->assertSame($document1->getScore(), $document2->getScore());
    }
}
