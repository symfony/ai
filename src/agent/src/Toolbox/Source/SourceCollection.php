<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Source;

/**
 * @implements \IteratorAggregate<int, Source>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SourceCollection implements \IteratorAggregate, \Countable
{
    /**
     * @param Source[] $sources
     */
    public function __construct(
        private array $sources = [],
    ) {
    }

    /**
     * @return Source[]
     */
    public function all(): array
    {
        return $this->sources;
    }

    public function add(Source $source): void
    {
        $this->sources[] = $source;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->sources);
    }

    public function count(): int
    {
        return \count($this->sources);
    }
}
