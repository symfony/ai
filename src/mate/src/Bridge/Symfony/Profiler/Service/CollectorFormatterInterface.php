<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Interface for formatting collector data for AI consumption.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @template TCollector of DataCollectorInterface
 */
interface CollectorFormatterInterface
{
    public function getName(): string;

    /**
     * @param TCollector $collector
     *
     * @return array<string, mixed>
     */
    public function format(DataCollectorInterface $collector): array;

    /**
     * @param TCollector $collector
     *
     * @return array<string, mixed>
     */
    public function getSummary(DataCollectorInterface $collector): array;
}
