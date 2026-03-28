<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ShippingOrder;

final class OrderItem
{
    public function __construct(
        public readonly string $name,
        public readonly int $quantity,
        public readonly float $price,
    ) {
    }
}
