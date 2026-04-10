<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @phpstan-implements \IteratorAggregate<int, ResultInterface>
 */
final class MultiPartResult extends BaseResult implements \IteratorAggregate
{
    /**
     * @param non-empty-list<ResultInterface> $results
     */
    public function __construct(
        private readonly array $results,
    ) {
    }

    /**
     * @return non-empty-list<ResultInterface>
     */
    public function getContent(): array
    {
        return $this->results;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->results);
    }

    public function asText(): string
    {
        return implode('', array_map(static fn (TextResult $result) => $result->getContent(), array_filter($this->results, static fn (ResultInterface $result) => $result instanceof TextResult)));
    }
}
