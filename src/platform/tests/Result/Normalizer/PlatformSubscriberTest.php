<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\Normalizer\PlatformSubscriber;
use Symfony\AI\Platform\Result\Normalizer\TextNormalizerInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

final class PlatformSubscriberTest extends TestCase
{
    public function testAppliesAllMatchingNormalizersInRegistrationOrder()
    {
        $subscriber = new PlatformSubscriber([
            $this->createNormalizer(static fn (string $text) => $text.'-first'),
            $this->createNormalizer(static fn (string $text) => $text.'-second'),
        ]);

        $event = $this->createResultEvent(new TextResult('value'));
        $subscriber->onResult($event);

        $this->assertInstanceOf(TextResult::class, $result = $event->getDeferredResult()->getResult());
        $this->assertSame('value-first-second', $result->getContent());
    }

    public function testSkipsNonMatchingNormalizers()
    {
        $subscriber = new PlatformSubscriber([
            $this->createNormalizer(static fn (string $text) => $text.'-applied', supports: false),
            $this->createNormalizer(static fn (string $text) => $text.'-also-applied'),
        ]);

        $event = $this->createResultEvent(new TextResult('value'));
        $subscriber->onResult($event);

        $this->assertInstanceOf(TextResult::class, $result = $event->getDeferredResult()->getResult());
        $this->assertSame('value-also-applied', $result->getContent());
    }

    public function testLeavesResultUntouchedWhenNoNormalizerMatches()
    {
        $original = new TextResult('value');
        $subscriber = new PlatformSubscriber([
            $this->createNormalizer(static fn (string $text) => $text.'-never', supports: false),
        ]);

        $event = $this->createResultEvent($original);
        $originalDeferred = $event->getDeferredResult();
        $subscriber->onResult($event);

        $this->assertSame($originalDeferred, $event->getDeferredResult());
        $this->assertSame($original, $event->getDeferredResult()->getResult());
    }

    public function testLeavesResultUntouchedWhenNormalizersProduceSameText()
    {
        $original = new TextResult('value');
        $subscriber = new PlatformSubscriber([
            $this->createNormalizer(static fn (string $text) => $text),
        ]);

        $event = $this->createResultEvent($original);
        $originalDeferred = $event->getDeferredResult();
        $subscriber->onResult($event);

        $this->assertSame($originalDeferred, $event->getDeferredResult());
        $this->assertSame($original, $event->getDeferredResult()->getResult());
    }

    public function testLeavesNonTextResultsUntouched()
    {
        $vectorResult = new VectorResult([new Vector([0.1, 0.2])]);

        $subscriber = new PlatformSubscriber([
            $this->createNormalizer(static fn (string $text) => $text.'-modified'),
        ]);

        $event = $this->createResultEvent($vectorResult);
        $originalDeferred = $event->getDeferredResult();
        $subscriber->onResult($event);

        $this->assertSame($originalDeferred, $event->getDeferredResult());
        $this->assertSame($vectorResult, $event->getDeferredResult()->getResult());
    }

    public function testNormalizesTextPartsInsideMultiPartResult()
    {
        $multiPart = new MultiPartResult([
            new TextResult('foo'),
            new VectorResult([new Vector([0.1, 0.2])]),
            new TextResult('bar'),
        ]);

        $subscriber = new PlatformSubscriber([
            $this->createNormalizer(static fn (string $text) => $text.'!'),
        ]);

        $event = $this->createResultEvent($multiPart);
        $subscriber->onResult($event);

        $this->assertInstanceOf(MultiPartResult::class, $result = $event->getDeferredResult()->getResult());
        $parts = $result->getContent();
        $this->assertCount(3, $parts);
        $this->assertInstanceOf(TextResult::class, $parts[0]);
        $this->assertSame('foo!', $parts[0]->getContent());
        $this->assertInstanceOf(VectorResult::class, $parts[1]);
        $this->assertInstanceOf(TextResult::class, $parts[2]);
        $this->assertSame('bar!', $parts[2]->getContent());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createResultEvent(ResultInterface $result, array $options = []): ResultEvent
    {
        $deferred = new DeferredResult(new PlainConverter($result), new InMemoryRawResult(), $options);

        return new ResultEvent(new Model('gpt-4'), $deferred, $options);
    }

    private function createNormalizer(\Closure $normalize, bool $supports = true): TextNormalizerInterface
    {
        return new class($normalize, $supports) implements TextNormalizerInterface {
            public function __construct(
                private readonly \Closure $normalize,
                private readonly bool $supports,
            ) {
            }

            public function supports(Model $model, ResultInterface $result, array $options): bool
            {
                return $this->supports;
            }

            public function normalize(string $text): string
            {
                return ($this->normalize)($text);
            }
        };
    }
}
