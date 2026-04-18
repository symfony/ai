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
use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\RetrieverInterface;
use Symfony\Component\Uid\Uuid;

final class SimilaritySearchTest extends TestCase
{
    public function testSearchWithResults()
    {
        $searchTerm = 'find similar documents';
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document1 = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata(['title' => 'Document 1', 'content' => 'First document content']),
        );
        $document2 = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata(['title' => 'Document 2', 'content' => 'Second document content']),
        );

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with($searchTerm)
            ->willReturn([$document1, $document2]);

        $similaritySearch = new SimilaritySearch($retriever);

        $result = $similaritySearch($searchTerm);

        $this->assertSame('Found documents with the following information:'.\PHP_EOL.'{"title":"Document 1","content":"First document content"}{"title":"Document 2","content":"Second document content"}', $result);
        $this->assertSame([$document1, $document2], $similaritySearch->getUsedDocuments());
    }

    public function testSearchWithoutResults()
    {
        $searchTerm = 'find nothing';

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with($searchTerm)
            ->willReturn([]);

        $similaritySearch = new SimilaritySearch($retriever);

        $result = $similaritySearch($searchTerm);

        $this->assertSame('No results found', $result);
        $this->assertSame([], $similaritySearch->getUsedDocuments());
    }

    public function testSearchWithSingleResult()
    {
        $searchTerm = 'specific query';
        $vector = new Vector([0.5, 0.6, 0.7]);

        $document = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata(['title' => 'Single Document', 'description' => 'Only one match']),
        );

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with($searchTerm)
            ->willReturn([$document]);

        $similaritySearch = new SimilaritySearch($retriever);

        $result = $similaritySearch($searchTerm);

        $this->assertSame('Found documents with the following information:'.\PHP_EOL.'{"title":"Single Document","description":"Only one match"}', $result);
        $this->assertSame([$document], $similaritySearch->getUsedDocuments());
    }

    public function testSearchWithCustomPromptTemplate()
    {
        $searchTerm = 'custom template query';
        $vector = new Vector([0.1, 0.2, 0.3]);

        $document = new VectorDocument(
            Uuid::v4(),
            $vector,
            new Metadata(['title' => 'Document 1']),
        );

        $retriever = $this->createMock(RetrieverInterface::class);
        $retriever->expects($this->once())
            ->method('retrieve')
            ->with($searchTerm)
            ->willReturn([$document]);

        $similaritySearch = new SimilaritySearch($retriever, 'Here are the relevant results:');

        $result = $similaritySearch($searchTerm);

        $this->assertSame('Here are the relevant results:'.\PHP_EOL.'{"title":"Document 1"}', $result);
    }
}
