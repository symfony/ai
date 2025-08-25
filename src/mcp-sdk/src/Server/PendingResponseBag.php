<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server;

use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Response;
use Symfony\Component\Clock\ClockInterface;

/**
 * @implements \IteratorAggregate<string|int, PendingResponse>
 */
final class PendingResponseBag implements \Countable, \IteratorAggregate
{
    /** @var array<string|int, PendingResponse> */
    private array $responses = [];

    public function __construct(
        private ClockInterface $clock,
        private \DateInterval $timeout,
    ) {
    }

    public function add(PendingResponse $pendingResponse): void
    {
        $this->responses[$pendingResponse->getId()] = $pendingResponse;
    }

    public function has(string|int $id): bool
    {
        return isset($this->responses[$id]);
    }

    /**
     * Resolve and remove a pending response by incoming response or error.
     *
     * @return bool true when a pending response was found and resolved
     */
    public function resolve(Response|Error $response): bool
    {
        $id = $response->id;

        if (!isset($this->responses[$id])) {
            return false;
        }

        $this->responses[$id]->resolve($response);
        unset($this->responses[$id]);

        return true;
    }

    /**
     * Garbage collect timed-out pending responses.
     *
     * @param (\Closure(PendingResponse, Error): void)|null $onTimeout Optional callback invoked per timed-out response
     *
     * @return int number of timed-out responses
     */
    public function gc(?\Closure $onTimeout = null): int
    {
        $now = $this->clock->now();
        $timedOut = 0;

        foreach ($this->responses as $id => $pending) {
            if ($pending->getSentAt()->add($this->timeout) < $now) {
                $error = Error::requestTimeout($pending->getId());
                $pending->resolve($error);
                unset($this->responses[$id]);
                ++$timedOut;

                if (null !== $onTimeout) {
                    $onTimeout($pending, $error);
                }
            }
        }

        return $timedOut;
    }

    public function remove(string|int $id): void
    {
        unset($this->responses[$id]);
    }

    public function clear(): void
    {
        $this->responses = [];
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->responses);
    }

    public function count(): int
    {
        return \count($this->responses);
    }
}
