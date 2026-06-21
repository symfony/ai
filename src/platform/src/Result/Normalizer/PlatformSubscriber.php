<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Normalizer;

use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Applies registered text normalizers to text-bearing results after invocation.
 *
 * Runs before structured-output processing so normalizers can clean up the
 * raw text (e.g. strip Markdown code fences) before it is decoded.
 *
 * Streaming results are intentionally left untouched.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PlatformSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<TextNormalizerInterface> $normalizers
     */
    public function __construct(
        private readonly iterable $normalizers,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ResultEvent::class => ['onResult', 10],
        ];
    }

    public function onResult(ResultEvent $event): void
    {
        $deferred = $event->getDeferredResult();
        $result = $deferred->getResult();

        $normalized = $this->normalizeResult($result, $event);

        if ($normalized === $result) {
            return;
        }

        $newDeferred = new DeferredResult(new PlainConverter($normalized), $deferred->getRawResult(), $event->getOptions());
        $newDeferred->getMetadata()->set($deferred->getMetadata()->all());

        $event->setDeferredResult($newDeferred);
    }

    private function normalizeResult(ResultInterface $result, ResultEvent $event): ResultInterface
    {
        if ($result instanceof TextResult) {
            return $this->normalizeText($result, $event);
        }

        if ($result instanceof MultiPartResult) {
            return $this->normalizeMultiPart($result, $event);
        }

        return $result;
    }

    private function normalizeText(TextResult $result, ResultEvent $event): TextResult
    {
        $original = $result->getContent();
        $text = $original;

        foreach ($this->normalizers as $normalizer) {
            if (!$normalizer->supports($event->getModel(), $result, $event->getOptions())) {
                continue;
            }

            $text = $normalizer->normalize($text);
        }

        if ($text === $original) {
            return $result;
        }

        return $result->withContent($text);
    }

    private function normalizeMultiPart(MultiPartResult $result, ResultEvent $event): MultiPartResult
    {
        $parts = $result->getContent();
        $newParts = [];
        $changed = false;

        foreach ($parts as $part) {
            if ($part instanceof TextResult) {
                $normalized = $this->normalizeText($part, $event);
                if ($normalized !== $part) {
                    $changed = true;
                }
                $newParts[] = $normalized;

                continue;
            }

            $newParts[] = $part;
        }

        if (!$changed) {
            return $result;
        }

        $rebuilt = new MultiPartResult($newParts);
        $rebuilt->getMetadata()->set($result->getMetadata()->all());

        if (null !== $rawResult = $result->getRawResult()) {
            $rebuilt->setRawResult($rawResult);
        }

        return $rebuilt;
    }
}
