<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures;

/**
 * @author Asrar ul haq nahvi <aszenz@gmail.com>
 */
class Field
{
    /**
     * @param list<int> $boundingBox2D A list of numbers representing the 2D coordinates of the bounding box, typically in the format [y_min, x_min, y_max, x_max]. Values are often in thousandths.
     */
    public function __construct(
        public mixed $value,
        public int $pageNo,
        public array $boundingBox2D,
    ) {
    }
}
