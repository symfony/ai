<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

/**
 * Base guard that applies to a fixed set of places.
 *
 * Concrete guards only implement {@see GuardInterface::allows()}; the places
 * the guard applies to are passed to the constructor. An empty list applies
 * the guard to every place.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class AbstractGuard implements GuardInterface
{
    /**
     * @param list<non-empty-string> $places Places this guard applies to; an empty list applies to every place
     */
    public function __construct(
        private readonly array $places = [],
    ) {
    }

    public function supports(string $place): bool
    {
        return [] === $this->places || \in_array($place, $this->places, true);
    }
}
