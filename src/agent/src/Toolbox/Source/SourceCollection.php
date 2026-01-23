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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Metadata\MergeableMetadataInterface;

/**
 * @implements \IteratorAggregate<int, Source>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SourceCollection implements MergeableMetadataInterface, \IteratorAggregate, \Countable
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

    public function merge(MergeableMetadataInterface $metadata): self
    {
        if (!$metadata instanceof self) {
            throw new InvalidArgumentException(\sprintf('Cannot merge "%s" with "%s".', self::class, $metadata::class));
        }

        return new self([...$this->sources, ...$metadata->sources]);
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
