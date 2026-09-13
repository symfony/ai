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

use Symfony\AI\Mate\Attribute\MateTool;
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
    #[MateTool(name: 'sample-add', title: 'Sample Add', description: 'Add two integers')]
    public function add(int $a, int $b = 0, bool $negate = false): string
    {
        $sum = $a + $b;
        if ($negate) {
            $sum = -$sum;
        }

        return ResponseEncoder::encode(['sum' => $sum]);
    }

    /**
     * @param string $text Text returned verbatim
     */
    #[MateTool(name: 'sample-echo', title: 'Sample Echo', description: 'Return plain text')]
    public function echoText(string $text): string
    {
        return $text;
    }

    /**
     * @param string $sku     Article number
     * @param string ...$tags Extra tags
     */
    #[MateTool(name: 'sample-tags', title: 'Sample Tags', description: 'Collect variadic tags')]
    public function tags(string $sku, string ...$tags): string
    {
        return ResponseEncoder::encode(['sku' => $sku, 'tags' => $tags]);
    }
}
