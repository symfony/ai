<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\MariaDb;

use OskarStark\Enum\Trait\Comparable;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
enum Distance: string
{
    use Comparable;

    case Cosine = 'cosine';
    case Euclidean = 'euclidean';
    case Distance = 'distance';

    public function getComparisonFunction(): string
    {
        return match ($this) {
            self::Cosine => 'VEC_DISTANCE_COSINE',
            self::Euclidean => 'VEC_DISTANCE_EUCLIDEAN',
            self::Distance => 'VEC_DISTANCE',
        };
    }
}
