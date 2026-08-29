<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine;

use OskarStark\Enum\Trait\Comparable;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum Distance: string
{
    use Comparable;

    case Cosine = 'cosine';
    case Euclidean = 'euclidean';
    case InnerProduct = 'inner_product';
}
