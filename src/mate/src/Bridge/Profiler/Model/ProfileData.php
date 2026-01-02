<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Profiler\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
class ProfileData
{
    /**
     * @param array<string, mixed> $collectors
     * @param array<string>        $children
     */
    public function __construct(
        public readonly string $token,
        public readonly ProfileIndex $index,
        public readonly array $collectors,
        public readonly array $children = [],
    ) {
    }

    public function getCollector(string $name): mixed
    {
        return $this->collectors[$name] ?? null;
    }

    public function hasCollector(string $name): bool
    {
        return isset($this->collectors[$name]);
    }
}
