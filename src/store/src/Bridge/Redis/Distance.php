<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Redis;

use OskarStark\Enum\Trait\Comparable;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
enum Distance: string
{
    use Comparable;

    case Cosine = 'COSINE';
    case L2 = 'L2';
    case Ip = 'IP';
}
