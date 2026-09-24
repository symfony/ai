<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Filesystem\Tests\Fixtures;

final class StatTrackingStreamWrapper
{
    public mixed $context;
    public static int $statCalls = 0;

    /**
     * @return array{mode: int}
     */
    public function url_stat(string $path, int $flags): array
    {
        ++self::$statCalls;

        return ['mode' => 0040777];
    }
}
