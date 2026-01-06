<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;

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
final class TraceablePlatform implements PlatformInterface
{
    /**
     * @var PlatformCallData[]
     */
    public array $calls = [];
    /**
     * @var \WeakMap<ResultInterface, string>
     */
    public \WeakMap $resultCache;

    public function __construct(
        private readonly PlatformInterface $platform,
    ) {
        $this->resultCache = new \WeakMap();
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $deferredResult = $this->platform->invoke($model, $input, $options);

        if ($input instanceof File) {
            $input = $input::class.': '.$input->getFormat();
        }

        if ($options['stream'] ?? false) {
            $originalStream = $deferredResult->asStream();
            $deferredResult = new DeferredResult(new PlainConverter($this->createTraceableStreamResult($originalStream)), $deferredResult->getRawResult(), $options);
        }

        $this->calls[] = [
            'model' => $model,
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

    private function createTraceableStreamResult(\Generator $originalStream): StreamResult
    {
        return $result = new StreamResult((function () use (&$result, $originalStream) {
            $this->resultCache[$result] = '';
            foreach ($originalStream as $chunk) {
                yield $chunk;
                if (\is_string($chunk)) {
                    $this->resultCache[$result] .= $chunk;
                }
            }
        })());
    }
}
