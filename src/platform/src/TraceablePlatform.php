<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type PlatformCallData array{
 *     model: string,
 *     input: array<mixed>|string|object,
 *     options: array<string, mixed>,
 *     result: DeferredResult,
 * }
 */
final class TraceablePlatform implements PlatformInterface, ResetInterface
{
    /**
     * @var PlatformCallData[]
     */
    private array $calls = [];
    /**
     * @var \WeakMap<ResultInterface, string>
     */
    private \WeakMap $resultCache;

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ?Stopwatch $stopwatch = null,
    ) {
        $this->resultCache = new \WeakMap();
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $modelName = $model instanceof Model ? $model->getName() : $model;
        $stopEvent = $this->startEvent($modelName);

        try {
            $deferredResult = $this->platform->invoke($model, $input, $options);
        } catch (\Throwable $exception) {
            $stopEvent();

            throw $exception;
        }

        if ($input instanceof File) {
            $input = $input::class.': '.$input->getFormat();
        }

        if ($options['stream'] ?? false) {
            $deferredResult = new DeferredResult(new PlainConverter($this->createTraceableStreamResult($deferredResult, $stopEvent)), $deferredResult->getRawResult(), $options);
        } else {
            // The request is only resolved on conversion, so the event spans until the result is available
            $deferredResult->onConvert(static function (ResultInterface $result) use ($stopEvent): ResultInterface {
                $stopEvent();

                return $result;
            });
            $deferredResult->onError(static function () use ($stopEvent): void {
                $stopEvent();
            });
        }

        $this->calls[] = [
            'model' => $modelName,
            'input' => \is_object($input) ? clone $input : $input,
            'options' => $options,
            'result' => $deferredResult,
        ];

        return $deferredResult;
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    /**
     * @return PlatformCallData[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return \WeakMap<ResultInterface, string>
     */
    public function getResultCache(): \WeakMap
    {
        return $this->resultCache;
    }

    public function reset(): void
    {
        $this->calls = [];
        $this->resultCache = new \WeakMap();
    }

    /**
     * @param \Closure(): void $stopEvent
     */
    private function createTraceableStreamResult(DeferredResult $originalStream, \Closure $stopEvent): StreamResult
    {
        // Keep writing to the cache the stream started with, as reset() may swap it mid-stream
        $resultCache = $this->resultCache;

        return $result = new StreamResult((static function () use (&$result, $originalStream, $stopEvent, $resultCache) {
            try {
                $resultCache[$result] = '';
                foreach ($originalStream->asStream() as $chunk) {
                    yield $chunk;
                    if ($chunk instanceof TextDelta) {
                        $resultCache[$result] .= $chunk->getText();
                    }
                }

                foreach ($originalStream->getResult()->getMetadata() as $key => $value) {
                    $result->getMetadata()->add($key, $value);
                }
            } finally {
                $stopEvent();
            }
        })());
    }

    /**
     * Starts a stopwatch event for the invocation and returns an idempotent closure stopping it.
     *
     * @return \Closure(): void
     */
    private function startEvent(string $modelName): \Closure
    {
        if (null === $this->stopwatch) {
            return static function (): void {};
        }

        $event = $this->stopwatch->start(\sprintf('ai.platform.invoke "%s"', $modelName), 'ai');
        $stopped = false;

        return static function () use ($event, &$stopped): void {
            // The profiler may have already closed the event when the result is consumed late
            if ($stopped || !$event->isStarted()) {
                return;
            }

            $stopped = true;
            $event->stop();
        };
    }
}
