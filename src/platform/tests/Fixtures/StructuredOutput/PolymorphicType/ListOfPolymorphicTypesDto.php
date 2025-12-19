<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType;

/**
 * Useful when you need to tell an agent that any of the items are acceptable types.
 * Real life example could be a list of possible analytical data visualization like charts or tables.
 */
final class ListOfPolymorphicTypesDto
{
    /**
     * @param list<ListItemDiscriminator> $items
     */
    public function __construct(public array $items)
    {
    }
}
