<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

/**
 * An immutable collection of arbitrary data objects handed to an agent.
 *
 * Each item is processed by a {@see ContextProcessorInterface} whose
 * {@see ContextProcessorInterface::supportedTypes()} matches the item's type.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements \IteratorAggregate<int, object>
 */
final class Context implements \Countable, \IteratorAggregate
{
    /**
     * @var list<object>
     */
    private readonly array $items;

    public function __construct(object ...$items)
    {
        $this->items = array_values($items);
    }

    public function with(object ...$items): self
    {
        return new self(...$this->items, ...$items);
    }

    public function merge(self $other): self
    {
        return new self(...$this->items, ...$other->items);
    }

    /**
     * @param class-string $type
     */
    public function without(string $type): self
    {
        return new self(...array_filter($this->items, static fn (object $item): bool => !$item instanceof $type));
    }

    /**
     * @param class-string|null $type
     *
     * @return list<object>
     */
    public function all(?string $type = null): array
    {
        if (null === $type) {
            return $this->items;
        }

        return array_values(array_filter($this->items, static fn (object $item): bool => $item instanceof $type));
    }

    /**
     * @param class-string $type
     */
    public function has(string $type): bool
    {
        foreach ($this->items as $item) {
            if ($item instanceof $type) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * @return \ArrayIterator<int, object>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
