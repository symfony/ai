<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test;

use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;
use Symfony\AI\Platform\Event\ResultErrorEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A fake implementation of PlatformInterface that returns fixed or callable responses.
 *
 * Useful for unit or integration testing without real API calls.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class InMemoryPlatform implements PlatformInterface
{
    private readonly ModelCatalogInterface $modelCatalog;
    private readonly ScriptedResponse $response;

    /**
     * The mock result can be a string or a callable that returns a string.
     * If it's a closure, it receives the model, input, and optionally options as parameters like a real platform call.
     *
     * With an event dispatcher, invoke() dispatches the same events {@see \Symfony\AI\Platform\Provider} does
     * (InvocationEvent, ResultEvent, and ResultConvertedEvent/ResultErrorEvent once the deferred result is
     * actually consumed), so listener-based code can be exercised against this fake the same way it runs in
     * production.
     */
    public function __construct(
        \Closure|string $mockResult,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->modelCatalog = new FallbackModelCatalog();
        $this->response = new ScriptedResponse($mockResult);
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        if (!$model instanceof Model) {
            $model = new class($model) extends Model {
                public function __construct(string $name)
                {
                    parent::__construct($name);
                }
            };
        }

        $invocationEvent = new InvocationEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($invocationEvent);

        $model = $invocationEvent->getModel();
        $input = $invocationEvent->getInput();
        $options = $invocationEvent->getOptions();

        // The script resolves eagerly (there is no real transport to defer), so a throwing
        // script is reported here rather than through the deferred result's onError, which
        // PlainConverter below never triggers.
        try {
            $result = $this->response->resolve($model, $input, $options);
        } catch (\Throwable $error) {
            $this->eventDispatcher?->dispatch(new ResultErrorEvent($model, $error, $options, $input));

            throw $error;
        }

        $deferredResult = $this->createDeferredResult($result, $options);

        $resultEvent = new ResultEvent($model, $deferredResult, $options, $input);
        $this->eventDispatcher?->dispatch($resultEvent);
        $deferredResult = $resultEvent->getDeferredResult();

        if (null !== $this->eventDispatcher) {
            $deferredResult->onConvert(function (ResultInterface $result) use ($model, $options, $input): ResultInterface {
                $event = new ResultConvertedEvent($model, $result, $options, $input);
                $this->eventDispatcher->dispatch($event);

                return $event->getResult();
            });
            $deferredResult->onError(function (\Throwable $error) use ($model, $options, $input): void {
                $this->eventDispatcher->dispatch(new ResultErrorEvent($model, $error, $options, $input));
            });
        }

        return $deferredResult;
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->modelCatalog;
    }

    /**
     * Creates a ResultPromise from a ResultInterface.
     *
     * @param ResultInterface      $result  The result to wrap in a promise
     * @param array<string, mixed> $options Additional options for the promise
     */
    private function createDeferredResult(ResultInterface $result, array $options): DeferredResult
    {
        $rawResult = $result->getRawResult() ?? new InMemoryRawResult(
            ['text' => $result->getContent()],
            [],
            (object) ['text' => $result->getContent()],
        );

        return new DeferredResult(new PlainConverter($result), $rawResult, $options);
    }
}
