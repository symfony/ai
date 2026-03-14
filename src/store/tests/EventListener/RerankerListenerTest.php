<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Event\PostQueryEvent;
use Symfony\AI\Store\EventListener\RerankerListener;
use Symfony\AI\Store\Reranker\RerankerInterface;

final class RerankerListenerTest extends TestCase
{
    public function testListenerCallsRerankerAndSetsDocuments()
    {
        $doc1 = new VectorDocument('doc-1', new Vector([0.1, 0.2]));
        $doc2 = new VectorDocument('doc-2', new Vector([0.3, 0.4]));
        $rerankedDoc = new VectorDocument('doc-2', new Vector([0.3, 0.4]), score: 0.95);

        $reranker = $this->createMock(RerankerInterface::class);
        $reranker->expects($this->once())
            ->method('rerank')
            ->with('test query', [$doc1, $doc2], 5)
            ->willReturn([$rerankedDoc]);

        $event = new PostQueryEvent('test query', [$doc1, $doc2]);

        $listener = new RerankerListener($reranker, 5);
        $listener($event);

        $documents = iterator_to_array($event->getDocuments());
        $this->assertCount(1, $documents);
        $this->assertSame(0.95, $documents[0]->getScore());
    }

    public function testTopKFromEventOptionsOverridesConstructorDefault()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $reranker = $this->createMock(RerankerInterface::class);
        $reranker->expects($this->once())
            ->method('rerank')
            ->with('test query', [$doc], 10)
            ->willReturn([$doc]);

        $event = new PostQueryEvent('test query', [$doc], ['topK' => 10]);

        $listener = new RerankerListener($reranker, 5);
        $listener($event);
    }
}
