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
 * in a database row or a Messenger message, pick it up in a worker, and resolve it with the
 * {@see JobClientInterface} of the bridge that started the job.
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
     * @param string               $id           the job identifier as issued by the provider
     * @param array<string, mixed> $data         provider-specific data needed to poll and fetch the job
     * @param string|null          $provider     the platform-level provider name, set by the job client
     *                                           of the bridge that creates the handle
     * @param int|null             $maxDuration  how long, in seconds, this kind of job may reasonably
     *                                           take at this provider - the bridge knows that video
     *                                           generation runs for minutes where speech takes seconds,
     *                                           and a caller usually does not
     * @param float|null           $pollInterval how often, in seconds, it is worth asking this provider
     *                                           about this kind of job - the same knowledge, on the other axis
     */
    public function __construct(
        private readonly string $id,
        private readonly array $data = [],
        private readonly ?string $provider = null,
        private readonly ?int $maxDuration = null,
        private readonly ?float $pollInterval = null,
    ) {
        if ('' === $this->id) {
            throw new InvalidArgumentException('A job handle needs a non-empty job identifier.');
        }

        if (null !== $this->maxDuration && $this->maxDuration < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum duration of a job must be at least one second, "%d" given.', $this->maxDuration));
        }

        if (null !== $this->pollInterval && $this->pollInterval <= 0) {
            throw new InvalidArgumentException(\sprintf('The poll interval of a job must be greater than zero, "%s" given.', $this->pollInterval));
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * The name of the provider that issued this handle, or null when the bridge did not state one.
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
     * How often it is worth polling this kind of job, in seconds - or null when the bridge has no
     * expectation. Honoured by a {@see JobRunner} unless its caller decided otherwise.
     */
    public function getPollInterval(): ?float
    {
        return $this->pollInterval;
    }

    /**
     * Returns a copy with the given data merged into the existing one.
     *
     * @param array<string, mixed> $data
     */
    public function withData(array $data): self
    {
        return new self($this->id, [...$this->data, ...$data], $this->provider, $this->maxDuration, $this->pollInterval);
    }

    /**
     * @return array{id: string, provider: string|null, data: array<string, mixed>, max_duration: int|null, poll_interval: float|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'data' => $this->data,
            'max_duration' => $this->maxDuration,
            'poll_interval' => $this->pollInterval,
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

        $pollInterval = $handle['poll_interval'] ?? null;

        // A handle serialized as JSON brings a whole-number interval back as an int.
        if (\is_int($pollInterval)) {
            $pollInterval = (float) $pollInterval;
        }

        if (null !== $pollInterval && !\is_float($pollInterval)) {
            throw new InvalidArgumentException(\sprintf('The "poll_interval" key of a serialized job handle must be a number or null, "%s" given.', get_debug_type($pollInterval)));
        }

        /* @var array<string, mixed> $data */
        return new self($id, $data, $provider, $maxDuration, $pollInterval);
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
            throw new InvalidArgumentException(\sprintf('A serialized job handle must be valid JSON: "%s"', $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException(\sprintf('A serialized job handle must decode to an array, "%s" given.', get_debug_type($decoded)));
        }

        return self::fromArray($decoded);
    }

    /**
     * @return array{id: string, provider: string|null, data: array<string, mixed>, max_duration: int|null, poll_interval: float|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
