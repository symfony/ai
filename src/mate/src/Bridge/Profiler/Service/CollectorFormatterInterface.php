<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Profiler\Service;

/**
 * Interface for formatting collector data for AI consumption.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
interface CollectorFormatterInterface
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function format(mixed $collectorData): array;

    /**
     * @return array<string, mixed>
     */
    public function getSummary(mixed $collectorData): array;
}
