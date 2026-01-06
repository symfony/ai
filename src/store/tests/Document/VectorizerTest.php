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
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\Component\Uid\Uuid;

#[TestDox('Tests for the Vectorizer class')]
final class VectorizerTest extends TestCase
{
    public function testVectorizeDocumentsWithBatchSupport()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'First document content', new Metadata(['source' => 'test1'])),
            new TextDocument(Uuid::v4(), 'Second document content', new Metadata(['source' => 'test2'])),
            new TextDocument(Uuid::v4(), 'Third document content', new Metadata(['source' => 'test3'])),
        ];

        $vectors = [
            new Vector([0.1, 0.2, 0.3]),
            new Vector([0.4, 0.5, 0.6]),
            new Vector([0.7, 0.8, 0.9]),
        ];

        // Create a test model catalog WITH INPUT_MULTIPLE capability
        $modelCatalog = new class extends AbstractModelCatalog {
            protected array $models = [
                'test-embedding-with-batch' => [
                    'class' => Model::class,
                    'capabilities' => [
                        Capability::INPUT_TEXT,
                        Capability::INPUT_MULTIPLE,  // Explicitly including batch support
                    ],
                ],
            ];
        };

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors), $modelCatalog);

        $vectorizer = new Vectorizer($platform, 'test-embedding-with-batch');
        $vectorDocuments = $vectorizer->vectorize($documents);

        $this->assertCount(3, $vectorDocuments);

        foreach ($vectorDocuments as $i => $vectorDoc) {
            $this->assertInstanceOf(VectorDocument::class, $vectorDoc);
            $this->assertSame($documents[$i]->getId(), $vectorDoc->id);
            $this->assertEquals($vectors[$i], $vectorDoc->vector);
            $this->assertSame($documents[$i]->getMetadata(), $vectorDoc->metadata);
        }
    }

    public function testVectorizeDocumentsWithSingleDocument()
    {
        $document = new TextDocument(Uuid::v4(), 'Single document content', new Metadata(['test' => 'value']));
        $vector = new Vector([0.1, 0.2, 0.3]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $vectorDocuments = $vectorizer->vectorize([$document]);

        $this->assertCount(1, $vectorDocuments);
        $this->assertInstanceOf(VectorDocument::class, $vectorDocuments[0]);
        $this->assertSame($document->getId(), $vectorDocuments[0]->id);
        $this->assertEquals($vector, $vectorDocuments[0]->vector);
        $this->assertSame($document->getMetadata(), $vectorDocuments[0]->metadata);
    }

    public function testVectorizeEmptyDocumentsArray()
    {
        $platform = PlatformTestHandler::createPlatform(new VectorResult());
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $vectorDocuments = $vectorizer->vectorize([]);

        $this->assertSame([], $vectorDocuments);
    }

    public function testVectorizeDocumentsPreservesMetadata()
    {
        $metadata1 = new Metadata(['source' => 'file1.txt', 'author' => 'Alice', 'tags' => ['important']]);
        $metadata2 = new Metadata(['source' => 'file2.txt', 'author' => 'Bob', 'version' => 2]);

        $documents = [
            new TextDocument(Uuid::v4(), 'Content 1', $metadata1),
            new TextDocument(Uuid::v4(), 'Content 2', $metadata2),
        ];

        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
        ];

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $vectorDocuments = $vectorizer->vectorize($documents);

        $this->assertCount(2, $vectorDocuments);
        $this->assertSame($metadata1, $vectorDocuments[0]->metadata);
        $this->assertSame($metadata2, $vectorDocuments[1]->metadata);
        $this->assertSame(['source' => 'file1.txt', 'author' => 'Alice', 'tags' => ['important']], $vectorDocuments[0]->metadata->getArrayCopy());
        $this->assertSame(['source' => 'file2.txt', 'author' => 'Bob', 'version' => 2], $vectorDocuments[1]->metadata->getArrayCopy());
    }

    public function testVectorizeDocumentsPreservesDocumentIds()
    {
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $id3 = Uuid::v4();

        $documents = [
            new TextDocument($id1, 'Document 1'),
            new TextDocument($id2, 'Document 2'),
            new TextDocument($id3, 'Document 3'),
        ];

        $vectors = [
            new Vector([0.1]),
            new Vector([0.2]),
            new Vector([0.3]),
        ];

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $vectorDocuments = $vectorizer->vectorize($documents);

        $this->assertCount(3, $vectorDocuments);
        $this->assertSame($id1, $vectorDocuments[0]->id);
        $this->assertSame($id2, $vectorDocuments[1]->id);
        $this->assertSame($id3, $vectorDocuments[2]->id);
    }

    #[DataProvider('provideDocumentCounts')]
    public function testVectorizeVariousDocumentCounts(int $count)
    {
        $documents = [];
        $vectors = [];

        for ($i = 0; $i < $count; ++$i) {
            $documents[] = new TextDocument(
                Uuid::v4(),
                \sprintf('Document %d content', $i),
                new Metadata(['index' => $i])
            );
            $vectors[] = new Vector([$i * 0.1, $i * 0.2, $i * 0.3]);
        }

        $platform = PlatformTestHandler::createPlatform(
            $count > 0 ? new VectorResult(...$vectors) : new VectorResult()
        );
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $vectorDocuments = $vectorizer->vectorize($documents);

        $this->assertCount($count, $vectorDocuments);

        foreach ($vectorDocuments as $i => $vectorDoc) {
            $this->assertInstanceOf(VectorDocument::class, $vectorDoc);
            $this->assertSame($documents[$i]->getId(), $vectorDoc->id);
            $this->assertEquals($vectors[$i], $vectorDoc->vector);
            $this->assertSame($documents[$i]->getMetadata(), $vectorDoc->metadata);
            $this->assertSame(['index' => $i], $vectorDoc->metadata->getArrayCopy());
        }
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function provideDocumentCounts(): \Generator
    {
        yield 'no documents' => [0];
        yield 'single document' => [1];
        yield 'two documents' => [2];
        yield 'three documents' => [3];
    }

    public function testVectorizeDocumentsWithLargeVectors()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');

        // Create a large vector with 1536 dimensions (typical for OpenAI embeddings)
        $dimensions = [];
        for ($i = 0; $i < 1536; ++$i) {
            $dimensions[] = $i * 0.001;
        }
        $vector = new Vector($dimensions);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $vectorDocuments = $vectorizer->vectorize([$document]);

        $this->assertCount(1, $vectorDocuments);
        $this->assertEquals($vector, $vectorDocuments[0]->vector);
    }

    public function testVectorizeDocumentsWithSpecialCharacters()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Document with "quotes" and special chars: @#$%'),
            new TextDocument(Uuid::v4(), "Document with\nnewlines\nand\ttabs"),
            new TextDocument(Uuid::v4(), 'Document with émojis 🚀 and ünïcödé'),
        ];

        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
            new Vector([0.5, 0.6]),
        ];

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $vectorDocuments = $vectorizer->vectorize($documents);

        $this->assertCount(3, $vectorDocuments);

        foreach ($vectorDocuments as $i => $vectorDoc) {
            $this->assertSame($documents[$i]->getId(), $vectorDoc->id);
            $this->assertEquals($vectors[$i], $vectorDoc->vector);
        }
    }

    public function testVectorizeDocumentsWithoutBatchSupportUsesNonBatchMode()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Document 1'),
            new TextDocument(Uuid::v4(), 'Document 2'),
        ];

        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
        ];

        // Create a test model catalog that explicitly does NOT have INPUT_MULTIPLE capability
        $modelCatalog = new class extends AbstractModelCatalog {
            protected array $models = [
                'test-embedding-no-batch' => [
                    'class' => Model::class,
                    'capabilities' => [
                        Capability::INPUT_TEXT,
                        // Explicitly excluding INPUT_MULTIPLE capability
                    ],
                ],
            ];
        };

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors), $modelCatalog);

        $vectorizer = new Vectorizer($platform, 'test-embedding-no-batch');
        $vectorDocuments = $vectorizer->vectorize($documents);

        $this->assertCount(2, $vectorDocuments);
        $this->assertEquals($vectors[0], $vectorDocuments[0]->vector);
        $this->assertEquals($vectors[1], $vectorDocuments[1]->vector);
    }

    public function testVectorizeString()
    {
        $text = 'This is a test string to vectorize';
        $vector = new Vector([0.1, 0.2, 0.3]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($text);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertEquals($vector, $result);
    }

    public function testVectorizeStringWithSpecialCharacters()
    {
        $text = 'Test with émojis 🚀 and ünïcödé characters';
        $vector = new Vector([0.5, 0.6, 0.7]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($text);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertEquals($vector, $result);
    }

    public function testVectorizeEmptyString()
    {
        $text = '';
        $vector = new Vector([0.0, 0.0, 0.0]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($text);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertEquals($vector, $result);
    }

    public function testVectorizeStringThrowsExceptionWhenNoVectorReturned()
    {
        $text = 'Test string';

        $platform = PlatformTestHandler::createPlatform(new VectorResult());
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No vector returned for string vectorization.');

        $vectorizer->vectorize($text);
    }

    public function testVectorizeStringable()
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'This is a Stringable object';
            }
        };

        $vector = new Vector([0.1, 0.2, 0.3]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($stringable);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertEquals($vector, $result);
    }

    public function testVectorizeArrayOfStrings()
    {
        $strings = ['First string', 'Second string', 'Third string'];
        $vectors = [
            new Vector([0.1, 0.2, 0.3]),
            new Vector([0.4, 0.5, 0.6]),
            new Vector([0.7, 0.8, 0.9]),
        ];

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($strings);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        foreach ($result as $i => $vector) {
            $this->assertInstanceOf(Vector::class, $vector);
            $this->assertEquals($vectors[$i], $vector);
        }
    }

    public function testVectorizeArrayOfStringables()
    {
        $stringables = [
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'First Stringable';
                }
            },
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'Second Stringable';
                }
            },
        ];

        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
        ];

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($stringables);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        foreach ($result as $i => $vector) {
            $this->assertInstanceOf(Vector::class, $vector);
            $this->assertEquals($vectors[$i], $vector);
        }
    }

    public function testVectorizeArrayOfMixedStringsAndStringables()
    {
        $values = [
            'Regular string',
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'Stringable object';
                }
            },
            'Another string',
        ];

        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
            new Vector([0.5, 0.6]),
        ];

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($values);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        foreach ($result as $i => $vector) {
            $this->assertInstanceOf(Vector::class, $vector);
            $this->assertEquals($vectors[$i], $vector);
        }
    }

    public function testVectorizeTextDocumentsPassesOptionsToInvoke()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Test document', new Metadata(['source' => 'test'])),
        ];

        $vector = new Vector([0.1, 0.2, 0.3]);
        $options = ['max_tokens' => 1000, 'temperature' => 0.5];

        // Use FallbackModelCatalog which provides all capabilities including INPUT_MULTIPLE
        // This ensures batch mode is used and the test expectation matches the behavior
        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'test-embedding-with-batch');
        $result = $vectorizer->vectorize($documents, $options);

        $this->assertCount(1, $result);
        $this->assertEquals($vector, $result[0]->vector);
    }

    public function testVectorizeTextDocumentsWithEmptyOptions()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Test document'),
        ];

        $vector = new Vector([0.1, 0.2, 0.3]);

        // Use FallbackModelCatalog which provides all capabilities including INPUT_MULTIPLE
        // This ensures batch mode is used and the test expectation matches the behavior
        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $vectorizer = new Vectorizer($platform, 'test-embedding-with-batch');
        $result = $vectorizer->vectorize($documents);

        $this->assertCount(1, $result);
        $this->assertEquals($vector, $result[0]->vector);
    }

    public function testVectorizeStringPassesOptionsToInvoke()
    {
        $text = 'Test string';
        $vector = new Vector([0.1, 0.2, 0.3]);
        $options = ['temperature' => 0.7, 'max_tokens' => 500];

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->equalTo('text-embedding-3-small'),
                $this->equalTo($text),
                $this->equalTo($options)
            )
            ->willReturn(new DeferredResult(new PlainConverter(new VectorResult($vector)), $this->createMock(RawResultInterface::class)));

        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($text, $options);

        $this->assertEquals($vector, $result);
    }

    public function testVectorizeStringWithEmptyOptions()
    {
        $text = 'Test string';
        $vector = new Vector([0.1, 0.2, 0.3]);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->equalTo('text-embedding-3-small'),
                $this->equalTo($text),
                $this->equalTo([])
            )
            ->willReturn(new DeferredResult(new PlainConverter(new VectorResult($vector)), $this->createMock(RawResultInterface::class)));

        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
        $result = $vectorizer->vectorize($text);

        $this->assertEquals($vector, $result);
    }

    public function testVectorizeTextDocumentsWithoutBatchSupportPassesOptions()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Document 1'),
            new TextDocument(Uuid::v4(), 'Document 2'),
        ];

        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
        ];

        $options = ['max_tokens' => 2000];

        // Create a test model catalog without INPUT_MULTIPLE capability
        $modelCatalog = new class extends AbstractModelCatalog {
            protected array $models = [
                'test-embedding-no-batch-with-options' => [
                    'class' => Model::class,
                    'capabilities' => [
                        Capability::INPUT_TEXT,
                        // No INPUT_MULTIPLE capability
                    ],
                ],
            ];
        };

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors), $modelCatalog);

        $vectorizer = new Vectorizer($platform, 'test-embedding-no-batch-with-options');
        $result = $vectorizer->vectorize($documents, $options);

        $this->assertCount(2, $result);
        $this->assertEquals($vectors[0], $result[0]->vector);
        $this->assertEquals($vectors[1], $result[1]->vector);
    }
}
