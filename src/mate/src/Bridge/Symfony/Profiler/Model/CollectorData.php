<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
class CollectorData
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $summary
     */
    public function __construct(
        public readonly string $name,
        public readonly array $data,
        public readonly array $summary,
    ) {
    }
}
