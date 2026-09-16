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
 * Built by a factory service in the fixture container, so the dump records no class to
 * reflect and its parameter names stay unknown.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class Unreflectable
{
    /**
     * @param array<string, mixed> $anything
     */
    public function __construct(
        public readonly array $anything = [],
    ) {
    }
}
