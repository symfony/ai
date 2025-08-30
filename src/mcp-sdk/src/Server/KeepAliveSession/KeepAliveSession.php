<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\KeepAliveSession;

use Symfony\AI\McpSdk\Server\KeepAliveSessionInterface;
use Symfony\Component\Clock\ClockInterface;

final class KeepAliveSession implements KeepAliveSessionInterface
{
    private bool $running = false;
    private \DateTimeImmutable $nextPingAt;

    public function __construct(
        private ClockInterface $clock,
        private \DateInterval $interval,
    ) {
        $this->nextPingAt = $this->clock->now()->add($this->interval);
    }

    public function start(): void
    {
        $this->running = true;
        $this->nextPingAt = $this->clock->now()->add($this->interval);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function tick(\Closure $callback): void
    {
        if (!$this->running) {
            return;
        }

        if ($this->clock->now() < $this->nextPingAt) {
            return;
        }

        $callback();

        $this->nextPingAt = $this->clock->now()->add($this->interval);
    }
}
