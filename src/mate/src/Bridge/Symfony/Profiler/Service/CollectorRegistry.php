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
 * Registry for collector formatters.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
final class CollectorRegistry
{
    /**
     * @var array<string, CollectorFormatterInterface<DataCollectorInterface>>
     */
    private array $formatters = [];

    /**
     * @param iterable<CollectorFormatterInterface<DataCollectorInterface>> $formatters
     */
    public function __construct(iterable $formatters = [])
    {
        foreach ($formatters as $formatter) {
            $this->register($formatter);
        }
    }

    /**
     * @param CollectorFormatterInterface<DataCollectorInterface> $formatter
     */
    public function register(CollectorFormatterInterface $formatter): void
    {
        $this->formatters[$formatter->getName()] = $formatter;
    }

    /**
     * @return CollectorFormatterInterface<DataCollectorInterface>|null
     */
    public function get(string $name): ?CollectorFormatterInterface
    {
        return $this->formatters[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    /**
     * @return array<string, CollectorFormatterInterface<DataCollectorInterface>>
     */
    public function all(): array
    {
        return $this->formatters;
    }
}
