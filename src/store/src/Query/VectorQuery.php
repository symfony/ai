<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Query;

use Symfony\AI\Platform\Vector\VectorInterface;

/**
 * Classic vector search query using semantic similarity.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class VectorQuery implements QueryInterface
{
    public function __construct(
        private readonly VectorInterface $vector,
    ) {
    }

    public function getVector(): VectorInterface
    {
        return $this->vector;
    }
}
