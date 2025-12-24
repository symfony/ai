<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Speech;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @implements \IteratorAggregate<Speech>
 */
final class SpeechBag implements \IteratorAggregate, \Countable
{
    /**
     * @var Speech[]
     */
    private array $speeches = [];

    public function add(Speech $speech): void
    {
        $this->speeches[$speech->getIdentifier()] = $speech;
    }

    public function get(string $identifier): Speech
    {
        return $this->speeches[$identifier] ?? throw new InvalidArgumentException(\sprintf('No speech with identifier "%s" found.', $identifier));
    }

    public function count(): int
    {
        return \count($this->speeches);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->speeches);
    }
}
