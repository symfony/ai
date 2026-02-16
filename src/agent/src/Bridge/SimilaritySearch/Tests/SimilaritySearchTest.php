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
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
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

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->expects($this->once())
            ->method('vectorize')
            ->with($searchTerm)
            ->willReturn($vector);

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('query')
            ->with($this->callback(static fn ($query) => $query instanceof VectorQuery && $query->getVector() === $vector))
            ->willReturn([$document1, $document2]);

        $similaritySearch = new SimilaritySearch($vectorizer, $store);

        $result = $similaritySearch($searchTerm);

        $this->assertSame('Found documents with following information:'.\PHP_EOL.'{"title":"Document 1","content":"First document content"}{"title":"Document 2","content":"Second document content"}', $result);
        $this->assertSame([$document1, $document2], $similaritySearch->usedDocuments);
    }

    public function testSearchWithoutResults()
    {
        $searchTerm = 'find nothing';
        $vector = new Vector([0.1, 0.2, 0.3]);

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->expects($this->once())
            ->method('vectorize')
            ->with($searchTerm)
            ->willReturn($vector);

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('query')
            ->with($this->callback(static fn ($query) => $query instanceof VectorQuery && $query->getVector() === $vector))
            ->willReturn([]);

        $similaritySearch = new SimilaritySearch($vectorizer, $store);

        $result = $similaritySearch($searchTerm);

        $this->assertSame('No results found', $result);
        $this->assertSame([], $similaritySearch->usedDocuments);
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

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->expects($this->once())
            ->method('vectorize')
            ->with($searchTerm)
            ->willReturn($vector);

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('query')
            ->with($this->callback(static fn ($query) => $query instanceof VectorQuery && $query->getVector() === $vector))
            ->willReturn([$document]);

        $similaritySearch = new SimilaritySearch($vectorizer, $store);

        $result = $similaritySearch($searchTerm);

        $this->assertSame('Found documents with following information:'.\PHP_EOL.'{"title":"Single Document","description":"Only one match"}', $result);
        $this->assertSame([$document], $similaritySearch->usedDocuments);
    }
}
