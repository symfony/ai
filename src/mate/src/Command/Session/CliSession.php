<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command\Session;

use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\SessionInterface;
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * CLI-specific session implementation for tool execution.
 *
 * Provides minimal session state for RequestContext injection.
 * Does not support pending requests or responses (throws error on sampling).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CliSession implements SessionInterface
{
    private Uuid $id;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];
    private SessionStoreInterface $store;

    public function __construct()
    {
        $this->id = Uuid::v4();

        $this->data = [
            '_mcp.pending_requests' => [],
            '_mcp.responses' => [],
            '_mcp.outgoing_queue' => [],
            '_mcp.active_request_meta' => null,
        ];

        $this->store = new InMemorySessionStore();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function save(): bool
    {
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value, bool $overwrite = true): void
    {
        if ($overwrite || !isset($this->data[$key])) {
            $this->data[$key] = $value;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function hydrate(array $attributes): void
    {
        $this->data = $attributes;
    }

    public function getStore(): SessionStoreInterface
    {
        return $this->store;
    }

    /**
     * @return array{id: string, data: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'data' => $this->data,
        ];
    }
}
