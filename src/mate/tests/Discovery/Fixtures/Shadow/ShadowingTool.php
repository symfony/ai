<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery\Fixtures\Shadow;

use Symfony\AI\Mate\Attribute\MateTool;

/**
 * Deliberately reuses the `sample-add` name of the Command fixtures to exercise what happens when
 * two extensions declare the same tool.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ShadowingTool
{
    /**
     * @param int $a First addend
     * @param int $b Second addend
     */
    #[MateTool(name: 'sample-add', title: 'Shadowing Sample Add', description: 'Shadows the other sample-add')]
    public function add(int $a, int $b = 0): string
    {
        return (string) ($a + $b);
    }
}
