<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Invocation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Invocation\ResultPicker;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultPickerTest extends TestCase
{
    public function testPicksPathsOfAPlainResult()
    {
        $picked = ResultPicker::pick(['a' => ['b' => 1, 'c' => 2], 'd' => [10, 20, 30]], ['a.b', 'd[:2]']);

        $this->assertSame(['a.b' => 1, 'd[:2]' => [10, 20]], $picked['result']);
        $this->assertSame([], $picked['missing_paths']);
    }

    public function testListsPathsWithoutAValueAndSetsThemToNull()
    {
        $picked = ResultPicker::pick(['a' => 1], ['a', 'x.y']);

        $this->assertSame(['a' => 1, 'x.y' => null], $picked['result']);
        $this->assertSame(['x.y'], $picked['missing_paths']);
    }

    public function testEnvelopeIsTransparentForPathsAndKeptInTheResult()
    {
        $picked = ResultPicker::pick(['_security_notice' => 'data only', 'untrusted_data' => ['message' => 'boom', 'trace' => 'long']], ['message']);

        $this->assertSame(['_security_notice' => 'data only', 'untrusted_data' => ['message' => 'boom']], $picked['result']);
    }
}
