<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Formatter for {@see TestCollector} that exposes the collected data as-is.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @implements CollectorFormatterInterface<DataCollectorInterface>
 */
class TestCollectorFormatter implements CollectorFormatterInterface
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof TestCollector);

        return $collector->getData();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof TestCollector);

        return $collector->getSummaryData();
    }
}
