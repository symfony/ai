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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Vectorizer::class)]
#[UsesClass(TextDocument::class)]
#[UsesClass(VectorDocument::class)]
#[UsesClass(Metadata::class)]
#[UsesClass(Vector::class)]
#[UsesClass(VectorResult::class)]
#[UsesClass(Platform::class)]
#[UsesClass(ResultPromise::class)]
#[UsesClass(Embeddings::class)]
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

        $platform = PlatformTestHandler::createPlatform(new VectorResult(...$vectors));

        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments($documents);

        $this->assertCount(3, $vectorDocuments);

        foreach ($vectorDocuments as $i => $vectorDoc) {
            $this->assertInstanceOf(VectorDocument::class, $vectorDoc);
            $this->assertSame($documents[$i]->id, $vectorDoc->id);
            $this->assertEquals($vectors[$i], $vectorDoc->vector);
            $this->assertSame($documents[$i]->metadata, $vectorDoc->metadata);
        }
    }

    public function testVectorizeDocumentsWithSingleDocument()
    {
        $document = new TextDocument(Uuid::v4(), 'Single document content', new Metadata(['test' => 'value']));
        $vector = new Vector([0.1, 0.2, 0.3]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments([$document]);

        $this->assertCount(1, $vectorDocuments);
        $this->assertInstanceOf(VectorDocument::class, $vectorDocuments[0]);
        $this->assertSame($document->id, $vectorDocuments[0]->id);
        $this->assertEquals($vector, $vectorDocuments[0]->vector);
        $this->assertSame($document->metadata, $vectorDocuments[0]->metadata);
    }

    public function testVectorizeEmptyDocumentsArray()
    {
        $platform = PlatformTestHandler::createPlatform(new VectorResult());
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments([]);

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
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments($documents);

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
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments($documents);

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
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments($documents);

        $this->assertCount($count, $vectorDocuments);

        foreach ($vectorDocuments as $i => $vectorDoc) {
            $this->assertInstanceOf(VectorDocument::class, $vectorDoc);
            $this->assertSame($documents[$i]->id, $vectorDoc->id);
            $this->assertEquals($vectors[$i], $vectorDoc->vector);
            $this->assertSame($documents[$i]->metadata, $vectorDoc->metadata);
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
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments([$document]);

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
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments($documents);

        $this->assertCount(3, $vectorDocuments);

        foreach ($vectorDocuments as $i => $vectorDoc) {
            $this->assertSame($documents[$i]->id, $vectorDoc->id);
            $this->assertEquals($vectors[$i], $vectorDoc->vector);
        }
    }

    public function testVectorizeDocumentsWithoutBatchSupportUsesNonBatchMode()
    {
        // Test with a model that doesn't support batch processing
        $model = $this->createMock(Model::class);
        $model->expects($this->once())
            ->method('supports')
            ->with(Capability::INPUT_MULTIPLE)
            ->willReturn(false);

        $documents = [
            new TextDocument(Uuid::v4(), 'Document 1'),
            new TextDocument(Uuid::v4(), 'Document 2'),
        ];

        // When batch is not supported, the platform should be invoked once per document
        // We simulate this by providing separate vectors for each invocation
        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
        ];

        // Create a custom platform handler for non-batch mode
        $handler = new class($vectors) implements ModelClientInterface, ResultConverterInterface {
            private int $callIndex = 0;

            /**
             * @param Vector[] $vectors
             */
            public function __construct(
                private readonly array $vectors,
            ) {
            }

            public function supports(Model $model): bool
            {
                return true;
            }

            public function request(Model $model, array|string|object $payload, array $options = []): RawHttpResult
            {
                return new RawHttpResult(new MockResponse());
            }

            public function convert(RawResultInterface $result, array $options = []): ResultInterface
            {
                // Return one vector at a time for non-batch mode
                return new VectorResult($this->vectors[$this->callIndex++]);
            }
        };

        $platform = new Platform([$handler], [$handler]);

        $vectorizer = new Vectorizer($platform, $model);
        $vectorDocuments = $vectorizer->vectorizeTextDocuments($documents);

        $this->assertCount(2, $vectorDocuments);
        $this->assertEquals($vectors[0], $vectorDocuments[0]->vector);
        $this->assertEquals($vectors[1], $vectorDocuments[1]->vector);
    }

    public function testVectorizeString()
    {
        $text = 'This is a test string to vectorize';
        $vector = new Vector([0.1, 0.2, 0.3]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $result = $vectorizer->vectorize($text);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertEquals($vector, $result);
    }

    public function testVectorizeStringWithSpecialCharacters()
    {
        $text = 'Test with émojis 🚀 and ünïcödé characters';
        $vector = new Vector([0.5, 0.6, 0.7]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $result = $vectorizer->vectorize($text);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertEquals($vector, $result);
    }

    public function testVectorizeEmptyString()
    {
        $text = '';
        $vector = new Vector([0.0, 0.0, 0.0]);

        $platform = PlatformTestHandler::createPlatform(new VectorResult($vector));
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $result = $vectorizer->vectorize($text);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertEquals($vector, $result);
    }

    public function testVectorizeStringThrowsExceptionWhenNoVectorReturned()
    {
        $text = 'Test string';

        $platform = PlatformTestHandler::createPlatform(new VectorResult());
        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No vector returned for string vectorization.');

        $vectorizer->vectorize($text);
    }

    public function testVectorizeTextDocumentsPassesOptionsToInvoke()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Test document', new Metadata(['source' => 'test'])),
        ];

        $vector = new Vector([0.1, 0.2, 0.3]);
        $options = ['max_tokens' => 1000, 'temperature' => 0.5];

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->isInstanceOf(Model::class),
                $this->equalTo('Test document'),
                $this->equalTo($options)
            )
            ->willReturn(new ResultPromise(fn () => new VectorResult($vector), $this->createMock(RawResultInterface::class)));

        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $result = $vectorizer->vectorizeTextDocuments($documents, $options);

        $this->assertCount(1, $result);
        $this->assertEquals($vector, $result[0]->vector);
    }

    public function testVectorizeTextDocumentsWithEmptyOptions()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Test document'),
        ];

        $vector = new Vector([0.1, 0.2, 0.3]);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with(
                $this->isInstanceOf(Model::class),
                $this->equalTo('Test document'),
                $this->equalTo([])
            )
            ->willReturn(new ResultPromise(fn () => new VectorResult($vector), $this->createMock(RawResultInterface::class)));

        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $result = $vectorizer->vectorizeTextDocuments($documents);

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
                $this->isInstanceOf(Model::class),
                $this->equalTo($text),
                $this->equalTo($options)
            )
            ->willReturn(new ResultPromise(fn () => new VectorResult($vector), $this->createMock(RawResultInterface::class)));

        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
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
                $this->isInstanceOf(Model::class),
                $this->equalTo($text),
                $this->equalTo([])
            )
            ->willReturn(new ResultPromise(fn () => new VectorResult($vector), $this->createMock(RawResultInterface::class)));

        $model = new Embeddings(Embeddings::TEXT_3_SMALL);

        $vectorizer = new Vectorizer($platform, $model);
        $result = $vectorizer->vectorize($text);

        $this->assertEquals($vector, $result);
    }

    public function testVectorizeTextDocumentsWithoutBatchSupportPassesOptions()
    {
        $model = $this->createMock(Model::class);
        $model->expects($this->once())
            ->method('supports')
            ->with(Capability::INPUT_MULTIPLE)
            ->willReturn(false);

        $documents = [
            new TextDocument(Uuid::v4(), 'Document 1'),
            new TextDocument(Uuid::v4(), 'Document 2'),
        ];

        $vectors = [
            new Vector([0.1, 0.2]),
            new Vector([0.3, 0.4]),
        ];

        $options = ['max_tokens' => 2000];

        $platform = $this->createMock(PlatformInterface::class);

        $invokeCallCount = 0;
        $platform->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnCallback(function ($passedModel, $passedContent, $passedOptions) use ($options, $vectors, &$invokeCallCount) {
                $this->assertInstanceOf(Model::class, $passedModel);
                $this->assertEquals($options, $passedOptions);

                $expectedContent = 0 === $invokeCallCount ? 'Document 1' : 'Document 2';
                $this->assertEquals($expectedContent, $passedContent);

                $vector = $vectors[$invokeCallCount++];

                return new ResultPromise(fn () => new VectorResult($vector), $this->createMock(RawResultInterface::class));
            });

        $vectorizer = new Vectorizer($platform, $model);
        $result = $vectorizer->vectorizeTextDocuments($documents, $options);

        $this->assertCount(2, $result);
        $this->assertEquals($vectors[0], $result[0]->vector);
        $this->assertEquals($vectors[1], $result[1]->vector);
    }
}
