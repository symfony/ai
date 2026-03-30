<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Reranker;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Reranker\Reranker;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RerankerTest extends TestCase
{
    public function testReranksDocumentsInCorrectOrder()
    {
        $platform = PlatformTestHandler::createPlatform(new RerankingResult(
            new RerankingEntry(0, 0.55),
            new RerankingEntry(1, 0.91),
        ));

        $reranker = new Reranker($platform, 'BAAI/bge-reranker-base?task=text-ranking');

        $doc0 = new VectorDocument('doc-0', new Vector([0.1, 0.2]), new Metadata([Metadata::KEY_TEXT => 'First document']));
        $doc1 = new VectorDocument('doc-1', new Vector([0.3, 0.4]), new Metadata([Metadata::KEY_TEXT => 'Second document']));

        $result = $reranker->rerank('test query', [$doc0, $doc1], 2);

        $this->assertCount(2, $result);
        $this->assertSame('doc-1', $result[0]->getId());
        $this->assertSame(0.91, $result[0]->getScore());
        $this->assertSame('doc-0', $result[1]->getId());
        $this->assertSame(0.55, $result[1]->getScore());
    }

    public function testReturnsEmptyForEmptyDocuments()
    {
        $platform = PlatformTestHandler::createPlatform();
        $reranker = new Reranker($platform, 'BAAI/bge-reranker-base?task=text-ranking');

        $result = $reranker->rerank('query', [], 5);

        $this->assertSame([], $result);
    }

    public function testTopKLimitsResults()
    {
        $platform = PlatformTestHandler::createPlatform(new RerankingResult(
            new RerankingEntry(0, 0.9),
            new RerankingEntry(1, 0.8),
            new RerankingEntry(2, 0.7),
        ));

        $reranker = new Reranker($platform, 'BAAI/bge-reranker-base?task=text-ranking');

        $docs = [
            new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'First'])),
            new VectorDocument('doc-1', new Vector([0.2]), new Metadata([Metadata::KEY_TEXT => 'Second'])),
            new VectorDocument('doc-2', new Vector([0.3]), new Metadata([Metadata::KEY_TEXT => 'Third'])),
        ];

        $result = $reranker->rerank('query', $docs, 2);

        $this->assertCount(2, $result);
        $this->assertSame('doc-0', $result[0]->getId());
        $this->assertSame('doc-1', $result[1]->getId());
    }

    public function testFallsBackToSourceWhenTextIsNull()
    {
        $platform = PlatformTestHandler::createPlatform(new RerankingResult(
            new RerankingEntry(0, 0.85),
        ));

        $reranker = new Reranker($platform, 'BAAI/bge-reranker-base?task=text-ranking');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_SOURCE => 'source-text']));
        $result = $reranker->rerank('query', [$doc], 1);

        $this->assertCount(1, $result);
        $this->assertSame('doc-0', $result[0]->getId());
        $this->assertSame(0.85, $result[0]->getScore());
    }
}
