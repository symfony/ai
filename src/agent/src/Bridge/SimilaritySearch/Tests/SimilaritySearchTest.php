<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SimilaritySearch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\SimilaritySearch\DocumentSource;
use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\FiberToolExecutor;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\RetrieverInterface;

final class SimilaritySearchTest extends TestCase
{
    public function testReturnsTheRetrievedDocuments()
    {
        $document1 = new VectorDocument('doc-1', new Vector([0.1]), new Metadata(['title' => 'Document 1']));
        $document2 = new VectorDocument('doc-2', new Vector([0.2]), new Metadata(['title' => 'Document 2']));

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with('find similar documents')
            ->willReturn([$document1, $document2]);

        $result = (new SimilaritySearch($retriever))('find similar documents');

        $this->assertSame([$document1, $document2], $result->getDocuments());
    }

    public function testHandsTheDocumentsToTheModelAsJsonWithTheirId()
    {
        $result = $this->convert(
            new VectorDocument('doc-1', new Vector([0.1]), new Metadata(['title' => 'Document 1', 'content' => 'First document content'])),
            new VectorDocument('doc-2', new Vector([0.2]), new Metadata(['title' => 'Document 2'])),
        );

        $this->assertSame('[{"id":"doc-1","title":"Document 1","content":"First document content"},{"id":"doc-2","title":"Document 2"}]', $result);
    }

    public function testHandsAnEmptyListToTheModelWithoutResults()
    {
        $this->assertSame('[]', $this->convert());
    }

    public function testPassesTheDocumentIdOverAnIdInTheMetadata()
    {
        $result = $this->convert(new VectorDocument('doc-5', new Vector([0.5]), new Metadata(['id' => 42, 'title' => 'Movie'])));

        $this->assertSame('[{"id":"doc-5","title":"Movie"}]', $result);
    }

    public function testAddsTheDocumentsAsSources()
    {
        $document = new VectorDocument('doc-1', new Vector([0.1]), new Metadata([
            Metadata::KEY_TITLE => 'Cats',
            Metadata::KEY_SOURCE => 'https://example.com/cats',
            Metadata::KEY_TEXT => 'All about cats',
        ]));

        $sources = $this->sourcesOf($document);

        $this->assertCount(1, $sources);
        $this->assertInstanceOf(DocumentSource::class, $sources[0]);
        $this->assertSame($document, $sources[0]->getDocument());
        $this->assertSame(['Cats', 'https://example.com/cats', 'All about cats'], [$sources[0]->getName(), $sources[0]->getReference(), $sources[0]->getContent()]);
    }

    public function testReferencesTheIdOfADocumentWithoutSource()
    {
        $sources = $this->sourcesOf(new VectorDocument(42, new Vector([0.1]), new Metadata([Metadata::KEY_TITLE => 'Cats'])));

        $this->assertSame(['Cats', '42', ''], [$sources[0]->getName(), $sources[0]->getReference(), $sources[0]->getContent()]);
    }

    public function testNamesADocumentWithoutTitleByItsReference()
    {
        $sources = $this->sourcesOf(new VectorDocument('doc-2', new Vector([0.2]), new Metadata(['genre' => 'drama'])));

        $this->assertSame(['doc-2', 'doc-2', ''], [$sources[0]->getName(), $sources[0]->getReference(), $sources[0]->getContent()]);
    }

    public function testCollectsTheSourcesOfEachCallInTheGivenCollection()
    {
        $retriever = $this->createStub(RetrieverInterface::class);
        $retriever->method('retrieve')->willReturnOnConsecutiveCalls(
            [new VectorDocument('doc-1', new Vector([0.1]))],
            [new VectorDocument('doc-2', new Vector([0.2]))],
        );
        $similaritySearch = new SimilaritySearch($retriever);

        $similaritySearch->setSourceCollection($first = new SourceCollection());
        $similaritySearch('cats');
        $similaritySearch->setSourceCollection($second = new SourceCollection());
        $similaritySearch('dogs');

        $this->assertSame(['doc-1'], array_map(static fn (Source $source): string => $source->getReference(), $first->all()));
        $this->assertSame(['doc-2'], array_map(static fn (Source $source): string => $source->getReference(), $second->all()));
    }

    public function testKeepsTheSourcesOfParallelCallsApartWhenTheRetrieverSuspends()
    {
        $retriever = $this->createStub(RetrieverInterface::class);
        $retriever->method('retrieve')->willReturnCallback(static function (string $query): array {
            \Fiber::suspend();

            return [new VectorDocument('doc-'.$query, new Vector([0.1]))];
        });
        $executor = new FiberToolExecutor(new Toolbox([new SimilaritySearch($retriever)]));

        $execution = $executor->execute([
            new ToolCall('call_1', 'similarity_search', ['searchTerm' => 'cats']),
            new ToolCall('call_2', 'similarity_search', ['searchTerm' => 'dogs']),
        ]);
        iterator_to_array($execution);

        $references = array_map(
            static fn (ToolResult $result): array => array_map(static fn (Source $source): string => $source->getReference(), $result->getSources()?->all() ?? []),
            $execution->getReturn(),
        );
        $this->assertSame([['doc-cats'], ['doc-dogs']], $references);
    }

    private function convert(VectorDocumentInterface ...$documents): ?string
    {
        $retriever = $this->createStub(RetrieverInterface::class);
        $retriever->method('retrieve')->willReturn($documents);

        $result = (new SimilaritySearch($retriever))('query');

        return (new ToolResultConverter())->convert(new ToolResult(new ToolCall('call_1', 'similarity_search'), $result));
    }

    /**
     * @return Source[]
     */
    private function sourcesOf(VectorDocumentInterface $document): array
    {
        $retriever = $this->createStub(RetrieverInterface::class);
        $retriever->method('retrieve')->willReturn([$document]);
        $similaritySearch = new SimilaritySearch($retriever);

        $similaritySearch('cats');

        return $similaritySearch->getSourceCollection()->all();
    }
}
