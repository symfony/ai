<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command\Fixtures;

use Symfony\AI\Mate\Attribute\AsTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SampleTool
{
    /**
     * @param int  $a      First addend
     * @param int  $b      Second addend
     * @param bool $negate Negate the result
     */
    #[AsTool(name: 'sample-add', title: 'Sample Add', description: 'Add two integers')]
    public function add(int $a, int $b = 0, bool $negate = false): string
    {
        $sum = $a + $b;
        if ($negate) {
            $sum = -$sum;
        }

        return ResponseEncoder::encode(['sum' => $sum]);
    }
}
