<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Job;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * A reference to an asynchronous job running at a provider.
 *
 * The point of this object is that it survives the process that started the job: it holds no client,
 * no connection and no closure, only the data needed to ask the provider about the job again. Put it
 * in a database row or a Messenger message, pick it up in a worker, and resolve it through
 * `Platform::getJobClient($handle->getProvider())`.
 *
 * The `data` map is provider-specific and opaque to the platform - a bridge stores in it whatever its
 * own {@see JobClientInterface} needs to poll and download (endpoint paths, file identifiers, the
 * expected MIME type, later the item keys of a batch).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobHandle implements \JsonSerializable
{
    /**
     * @param string               $id          the job identifier as issued by the provider
     * @param array<string, mixed> $data        provider-specific data needed to poll and fetch the job
     * @param string|null          $provider    the platform-level provider name; filled in by `Provider`
     *                                          after conversion, since a converter does not know under
     *                                          which name its provider was registered
     * @param int|null             $maxDuration how long, in seconds, this kind of job may reasonably
     *                                          take at this provider - the bridge knows that video
     *                                          generation runs for minutes where speech takes seconds,
     *                                          and a caller usually does not
     */
    public function __construct(
        private readonly string $id,
        private readonly array $data = [],
        private readonly ?string $provider = null,
        private readonly ?int $maxDuration = null,
    ) {
        if ('' === $this->id) {
            throw new InvalidArgumentException('A job handle needs a non-empty job identifier.');
        }

        if (null !== $this->maxDuration && $this->maxDuration < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum duration of a job must be at least one second, "%d" given.', $this->maxDuration));
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * The provider name to resolve this handle against, or null while the handle has not passed
     * through a `Provider` yet.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * How long this kind of job may reasonably take, in seconds, as far as the bridge knows - or
     * null when it has no expectation. A {@see JobRunner} waits that long unless its caller decided
     * otherwise, so a job that runs for minutes does not have to be waited for by a caller who knows
     * nothing about the provider's timings.
     */
    public function getMaxDuration(): ?int
    {
        return $this->maxDuration;
    }

    /**
     * Returns a copy bound to the given provider name.
     */
    public function withProvider(string $provider): self
    {
        return new self($this->id, $this->data, $provider, $this->maxDuration);
    }

    /**
     * Returns a copy with the given data merged into the existing one.
     *
     * @param array<string, mixed> $data
     */
    public function withData(array $data): self
    {
        return new self($this->id, [...$this->data, ...$data], $this->provider, $this->maxDuration);
    }

    /**
     * @return array{id: string, provider: string|null, data: array<string, mixed>, max_duration: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'data' => $this->data,
            'max_duration' => $this->maxDuration,
        ];
    }

    /**
     * Rebuilds a handle from {@see toArray()}, e.g. after reading it back from storage.
     *
     * @param array<string, mixed> $handle
     */
    public static function fromArray(array $handle): self
    {
        $id = $handle['id'] ?? null;

        if (!\is_string($id) || '' === $id) {
            throw new InvalidArgumentException('A serialized job handle needs a non-empty "id" key.');
        }

        $provider = $handle['provider'] ?? null;

        if (null !== $provider && !\is_string($provider)) {
            throw new InvalidArgumentException(\sprintf('The "provider" key of a serialized job handle must be a string or null, "%s" given.', get_debug_type($provider)));
        }

        $data = $handle['data'] ?? [];

        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('The "data" key of a serialized job handle must be an array, "%s" given.', get_debug_type($data)));
        }

        $maxDuration = $handle['max_duration'] ?? null;

        if (null !== $maxDuration && !\is_int($maxDuration)) {
            throw new InvalidArgumentException(\sprintf('The "max_duration" key of a serialized job handle must be an integer or null, "%s" given.', get_debug_type($maxDuration)));
        }

        /* @var array<string, mixed> $data */
        return new self($id, $data, $provider, $maxDuration);
    }

    /**
     * The handle as a single string, for storage that holds one column rather than a structure.
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR);
    }

    /**
     * Rebuilds a handle from {@see toString()}.
     */
    public static function fromString(string $handle): self
    {
        try {
            $decoded = json_decode($handle, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException(\sprintf('A serialized job handle must be valid JSON: %s', $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException(\sprintf('A serialized job handle must decode to an array, "%s" given.', get_debug_type($decoded)));
        }

        return self::fromArray($decoded);
    }

    /**
     * @return array{id: string, provider: string|null, data: array<string, mixed>, max_duration: int|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
