<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Recipe\Data;

final class Ingredient
{
    /**
     * @var string Name of the ingredient
     */
    public string $name;

    /**
     * @var string Quantity of the ingredient (e.g., "2 cups", "150g")
     */
    public string $quantity;

    public function toString(): string
    {
        return \sprintf('%s of %s', $this->quantity, $this->name);
    }
}
