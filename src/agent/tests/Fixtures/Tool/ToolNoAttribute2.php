<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

final class ToolNoAttribute2
{
    /**
     * @param int $id     the ID of the product
     * @param int $amount the number of products
     */
    public function buy(int $id, int $amount): string
    {
        return \sprintf('You bought %d of product %d.', $amount, $id);
    }

    /**
     * @param string $orderId the ID of the order
     */
    public function cancel(string $orderId): string
    {
        return \sprintf('You canceled order %s.', $orderId);
    }
}
