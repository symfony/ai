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

class ShippingOrder
{
    public string $recipientName;
    public ShippingPriority $priority;
    public \DateTimeImmutable $deliverBy;

    /** @var Address */
    public $address;

    /** @var OrderItem[] */
    public array $items;
}
