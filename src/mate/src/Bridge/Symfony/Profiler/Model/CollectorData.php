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
        private readonly string $name,
        private readonly array $data,
        private readonly array $summary,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return $this->summary;
    }
}
