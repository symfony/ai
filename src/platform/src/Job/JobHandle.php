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
     * @param string               $id       the job identifier as issued by the provider
     * @param array<string, mixed> $data     provider-specific data needed to poll and fetch the job
     * @param string|null          $provider the platform-level provider name; filled in by `Provider`
     *                                       after conversion, since a converter does not know under
     *                                       which name its provider was registered
     */
    public function __construct(
        private readonly string $id,
        private readonly array $data = [],
        private readonly ?string $provider = null,
    ) {
        if ('' === $this->id) {
            throw new InvalidArgumentException('A job handle needs a non-empty job identifier.');
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
     * Returns a copy bound to the given provider name.
     */
    public function withProvider(string $provider): self
    {
        return new self($this->id, $this->data, $provider);
    }

    /**
     * Returns a copy with the given data merged into the existing one.
     *
     * @param array<string, mixed> $data
     */
    public function withData(array $data): self
    {
        return new self($this->id, [...$this->data, ...$data], $this->provider);
    }

    /**
     * @return array{id: string, provider: string|null, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'data' => $this->data,
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

        /* @var array<string, mixed> $data */
        return new self($id, $data, $provider);
    }

    /**
     * @return array{id: string, provider: string|null, data: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
