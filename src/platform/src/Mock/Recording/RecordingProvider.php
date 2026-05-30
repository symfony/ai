<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock\Recording;

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
 * runs replay it offline — the inner provider is never called. The mode can be forced with the
 * explicit `$record` constructor argument.
 *
 * On replay the result is rebuilt from the cassette, so the bridge {@see \Symfony\AI\Platform\ResultConverter}
 * runs live only at record time. For tests that must exercise bridge internals (converter/formatter)
 * offline, use {@see \Symfony\AI\Platform\Mock\Http\CassetteHttpClient} instead.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RecordingProvider implements ProviderInterface
{
    private readonly bool $record;

    /**
     * @param bool|null $record whether to record (`true`) or replay (`false`); defaults to recording
     *                          when the cassette file does not exist yet and replaying otherwise
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly Cassette $cassette,
        ?bool $record = null,
    ) {
        $this->record = $record ?? !$cassette->exists();
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function supports(string $modelName): bool
    {
        return $this->provider->supports($modelName);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->provider->getModelCatalog();
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $signature = self::signature($model, $input, $options);

        if ($this->record) {
            $result = $this->provider->invoke($model, $input, $options)->getResult();
            $this->cassette->record($model, $signature, ResultSerializer::toArray($result));
        } else {
            $result = ResultSerializer::fromArray($this->cassette->match($signature));
        }

        return self::createDeferredResult($result, $options);
    }

    /**
     * Reconstructs a {@see DeferredResult} from a finished result, mirroring
     * {@see \Symfony\AI\Platform\Test\InMemoryPlatform}.
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
     * @param array<mixed>|string|object $input
     * @param array<string, mixed>       $options
     */
    private static function signature(string $model, array|string|object $input, array $options): string
    {
        try {
            $payload = json_encode([$model, $input, $options], \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $payload = serialize([$model, $input, $options]);
        }

        return hash('xxh128', $payload);
    }
}
