<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres;

use OskarStark\Enum\Trait\Comparable;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
enum Distance: string
{
    use Comparable;

    case Cosine = 'cosine';
    case InnerProduct = 'inner_product';
    case L1 = 'l1';
    case L2 = 'l2';

    public function getComparisonSign(): string
    {
        return match ($this) {
            self::Cosine => '<=>',
            self::InnerProduct => '<#>',
            self::L1 => '<+>',
            self::L2 => '<->',
        };
    }
}
