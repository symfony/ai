<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\HybridRetriever;
use Symfony\AI\Store\Reranker\RerankerInterface;
use Symfony\AI\Store\RetrieverInterface;

final class HybridRetrieverTest extends TestCase
{
    public function testRrfScoringWithKnownRanks()
    {
        $docA = new VectorDocument('doc-a', new Vector([0.1, 0.2]), new Metadata([Metadata::KEY_TEXT => 'Document A']));
        $docB = new VectorDocument('doc-b', new Vector([0.3, 0.4]), new Metadata([Metadata::KEY_TEXT => 'Document B']));
        $docC = new VectorDocument('doc-c', new Vector([0.5, 0.6]), new Metadata([Metadata::KEY_TEXT => 'Document C']));

        // Vector retriever: A=rank1, B=rank2
        $vectorRetriever = $this->createMock(RetrieverInterface::class);
        $vectorRetriever->method('retrieve')->willReturn([$docA, $docB]);

        // Text retriever: B=rank1, C=rank2
        $textRetriever = $this->createMock(RetrieverInterface::class);
        $textRetriever->method('retrieve')->willReturn([$docB, $docC]);

        $hybridRetriever = new HybridRetriever(
            vectorRetriever: $vectorRetriever,
            textRetriever: $textRetriever,
            rrfK: 60,
            topK: 3,
        );

        $results = iterator_to_array($hybridRetriever->retrieve('test query'));

        // docB appears in both lists (rank 2 in vector, rank 1 in text) so should score highest
        $this->assertCount(3, $results);
        $this->assertSame('doc-b', $results[0]->getId());
    }

    public function testResultsAreLimitedToTopKWithoutReranker()
    {
        $docs = [];
        for ($i = 0; $i < 5; ++$i) {
            $docs[] = new VectorDocument('doc-'.$i, new Vector([0.1 * $i, 0.2 * $i]));
        }

        $vectorRetriever = $this->createMock(RetrieverInterface::class);
        $vectorRetriever->method('retrieve')->willReturn($docs);

        $textRetriever = $this->createMock(RetrieverInterface::class);
        $textRetriever->method('retrieve')->willReturn([]);

        $hybridRetriever = new HybridRetriever(
            vectorRetriever: $vectorRetriever,
            textRetriever: $textRetriever,
            topK: 3,
        );

        $results = iterator_to_array($hybridRetriever->retrieve('test query'));

        $this->assertCount(3, $results);
    }

    public function testWithRerankerDelegatesReranking()
    {
        $docA = new VectorDocument('doc-a', new Vector([0.1, 0.2]));
        $docB = new VectorDocument('doc-b', new Vector([0.3, 0.4]));
        $rerankedDoc = new VectorDocument('doc-a', new Vector([0.1, 0.2]), new Metadata(), 0.95);

        $vectorRetriever = $this->createMock(RetrieverInterface::class);
        $vectorRetriever->method('retrieve')->willReturn([$docA, $docB]);

        $textRetriever = $this->createMock(RetrieverInterface::class);
        $textRetriever->method('retrieve')->willReturn([]);

        $reranker = $this->createMock(RerankerInterface::class);
        $reranker->expects($this->once())
            ->method('rerank')
            ->with('test query', $this->isType('array'), 3)
            ->willReturn([$rerankedDoc]);

        $hybridRetriever = new HybridRetriever(
            vectorRetriever: $vectorRetriever,
            textRetriever: $textRetriever,
            reranker: $reranker,
            topK: 3,
        );

        $results = iterator_to_array($hybridRetriever->retrieve('test query'));

        $this->assertCount(1, $results);
        $this->assertSame(0.95, $results[0]->getScore());
    }

    public function testRrfScoresAreUpdatedOnDocuments()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $vectorRetriever = $this->createMock(RetrieverInterface::class);
        $vectorRetriever->method('retrieve')->willReturn([$doc]);

        $textRetriever = $this->createMock(RetrieverInterface::class);
        $textRetriever->method('retrieve')->willReturn([$doc]);

        $hybridRetriever = new HybridRetriever(
            vectorRetriever: $vectorRetriever,
            textRetriever: $textRetriever,
            rrfK: 60,
            topK: 5,
        );

        $results = iterator_to_array($hybridRetriever->retrieve('test query'));

        $this->assertCount(1, $results);
        // Score should be sum of RRF contributions from both lists: 2 * (1 / (60 + 0 + 1))
        $expectedScore = 2.0 / 61.0;
        $this->assertEqualsWithDelta($expectedScore, $results[0]->getScore(), 0.0001);
    }

    public function testCandidateCountIsPassedToRetrievers()
    {
        $vectorRetriever = $this->createMock(RetrieverInterface::class);
        $vectorRetriever->expects($this->once())
            ->method('retrieve')
            ->with('query', ['limit' => 20])
            ->willReturn([]);

        $textRetriever = $this->createMock(RetrieverInterface::class);
        $textRetriever->expects($this->once())
            ->method('retrieve')
            ->with('query', ['limit' => 20])
            ->willReturn([]);

        $hybridRetriever = new HybridRetriever(
            vectorRetriever: $vectorRetriever,
            textRetriever: $textRetriever,
            candidateCount: 20,
        );

        iterator_to_array($hybridRetriever->retrieve('query'));
    }
}
