<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Vector;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class NullVector implements VectorInterface
{
    public function getData(): array
    {
        throw new RuntimeException('getData() method cannot be called on a NullVector.');
    }

    public function getDimensions(): int
    {
        throw new RuntimeException('getDimensions() method cannot be called on a NullVector.');
    }
}
