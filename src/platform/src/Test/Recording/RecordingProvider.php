<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test\Recording;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Decorates a real provider to record its result once and replay it offline in later test runs.
 *
 * By default recording happens automatically when the {@see Cassette} file does not exist yet:
 * the inner provider is invoked against the live API, its finished {@see ResultInterface} is
 * serialized to the cassette, and the same result is returned. Once the cassette exists, later
 * runs replay it offline and the inner provider is never called. The mode can be forced with the
 * explicit `$record` constructor argument.
 *
 * On replay the result is rebuilt from the cassette, so a bridge's `ResultConverter` runs live only
 * at record time. A test that must exercise bridge internals offline needs to record at the HTTP
 * boundary instead.
 *
 * @phpstan-type SignatureCallback \Closure(string|Model, array<mixed>|string|object, array<string, mixed>): string
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RecordingProvider implements ProviderInterface
{
    private readonly bool $record;

    /**
     * @var SignatureCallback
     */
    private readonly \Closure $signature;

    /**
     * A custom `$signature` derives the key an interaction is recorded and matched under. Pass one to
     * normalize values that legitimately differ between runs (timestamps, ids, retrieved context) and
     * would otherwise miss their own recording.
     *
     * @param bool|null              $record    whether to record (`true`) or replay (`false`); defaults to
     *                                          recording when the cassette file does not exist yet
     * @param SignatureCallback|null $signature
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly Cassette $cassette,
        ?bool $record = null,
        ?\Closure $signature = null,
    ) {
        $this->record = $record ?? !$cassette->exists();
        $this->signature = $signature ?? self::defaultSignature(...);
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function supports(string|Model $model): bool
    {
        return $this->provider->supports($model);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->provider->getModelCatalog();
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $signature = ($this->signature)($model, $input, $options);

        if ($this->record) {
            $result = $this->provider->invoke($model, $input, $options)->getResult();
            $this->cassette->record($model instanceof Model ? $model->getName() : $model, $signature, ResultSerializer::toArray($result));
        } else {
            $result = ResultSerializer::fromArray($this->cassette->match($signature));
        }

        return self::createDeferredResult($result, $options);
    }

    /**
     * Reconstructs a {@see DeferredResult} from a finished result.
     *
     * @param array<string, mixed> $options
     */
    private static function createDeferredResult(ResultInterface $result, array $options): DeferredResult
    {
        $rawResult = $result->getRawResult() ?? new InMemoryRawResult(
            ['text' => $result->getContent()],
            [],
            (object) ['text' => $result->getContent()],
        );

        return new DeferredResult(new PlainConverter($result), $rawResult, $options);
    }

    /**
     * A fully defined model is reduced to its name and options, so an equivalent model instance
     * built by a later run matches the recorded interaction. Everything else is hashed verbatim,
     * so an input that varies between runs needs a custom signature (see the constructor).
     *
     * @param array<mixed>|string|object $input
     * @param array<string, mixed>       $options
     */
    private static function defaultSignature(string|Model $model, array|string|object $input, array $options): string
    {
        $normalizedModel = $model instanceof Model ? [$model->getName(), $model->getOptions()] : $model;

        try {
            $payload = json_encode([$normalizedModel, $input, $options], \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $payload = serialize([$normalizedModel, $input, $options]);
        }

        return hash('xxh128', $payload);
    }
}
