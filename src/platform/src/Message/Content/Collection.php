<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

use Symfony\AI\Platform\Exception\LogicException;

final class Collection extends Content implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @var ContentInterface[]
     */
    private readonly array $content;

    public function __construct(ContentInterface ...$content)
    {
        $this->content = $content;
    }

    /**
     * @return ContentInterface[]
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @return \Traversable<ContentInterface>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->content;
    }

    public function count(): int
    {
        return \count($this->content);
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->content);
    }

    public function offsetGet(mixed $offset): ContentInterface
    {
        return $this->content[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Not allowed set values');
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->content[$offset]);
    }
}
