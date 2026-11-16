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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Event\PostQueryEvent;
use Symfony\AI\Store\Event\PreQueryEvent;
use Symfony\AI\Store\EventListener\RerankerListener;
use Symfony\AI\Store\Exception\InvalidArgumentException;
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

    public function testLimitFromEventOptionsOverridesConstructorDefault()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $reranker = $this->createMock(RerankerInterface::class);
        $reranker->expects($this->once())
            ->method('rerank')
            ->with('test query', [$doc], 10)
            ->willReturn([$doc]);

        $event = new PostQueryEvent('test query', [$doc], ['limit' => 10]);

        $listener = new RerankerListener($reranker, 5);
        $listener($event);
    }

    public function testOverFetchesTheLimitAndReranksDownToIt()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $reranker = $this->createMock(RerankerInterface::class);
        $reranker->expects($this->once())
            ->method('rerank')
            ->with('test query', [$doc], 3)
            ->willReturn([$doc]);

        $listener = new RerankerListener($reranker, 5, overFetchFactor: 4);

        $preQueryEvent = new PreQueryEvent('test query', ['limit' => 3, 'semanticRatio' => 0.5]);
        $listener($preQueryEvent);

        $this->assertSame(['limit' => 12, 'semanticRatio' => 0.5], $preQueryEvent->getOptions());

        $listener(new PostQueryEvent('test query', [$doc], $preQueryEvent->getOptions()));
    }

    public function testOverFetchesTheConstructorDefaultWithoutLimit()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $reranker = $this->createMock(RerankerInterface::class);
        $reranker->expects($this->once())
            ->method('rerank')
            ->with('test query', [$doc], 5)
            ->willReturn([$doc]);

        $listener = new RerankerListener($reranker, 5, overFetchFactor: 4);

        $preQueryEvent = new PreQueryEvent('test query');
        $listener($preQueryEvent);

        $this->assertSame(['limit' => 20], $preQueryEvent->getOptions());

        $listener(new PostQueryEvent('test query', [$doc], $preQueryEvent->getOptions()));
    }

    public function testResetForgetsTheOverFetchedLimit()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $reranker = $this->createMock(RerankerInterface::class);
        $reranker->expects($this->once())
            ->method('rerank')
            ->with('test query', [$doc], 7)
            ->willReturn([$doc]);

        $listener = new RerankerListener($reranker, 5, overFetchFactor: 4);
        $listener(new PreQueryEvent('test query', ['limit' => 3]));
        $listener->reset();

        $listener(new PostQueryEvent('test query', [$doc], ['limit' => 7]));
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function testThrowsOnOverFetchFactorBelowOne(int $overFetchFactor)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The over-fetch factor must be at least 1, %d given.', $overFetchFactor));

        new RerankerListener($this->createStub(RerankerInterface::class), overFetchFactor: $overFetchFactor);
    }
}
