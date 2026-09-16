<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Service;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class Storage
{
    /**
     * @param array<string, mixed> $connectionOptions
     * @param iterable<object>     $plugins
     */
    public function __construct(
        public readonly array $connectionOptions = [],
        public readonly iterable $plugins = [],
    ) {
    }
}
