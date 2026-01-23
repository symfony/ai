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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

final class Recipe
{
    /**
     * @var string Name of the recipe
     */
    public string $name;

    /**
     * @var int Duration in minutes
     */
    #[With(minimum: 5, maximum: 240)]
    public int $duration;

    /**
     * @var string Difficulty level of the recipe
     */
    #[With(enum: ['Beginner', 'Intermediate', 'Advanced'])]
    public string $level;

    /**
     * @var string Dietary preference
     */
    #[With(enum: ['Vegetarian', 'Vegan', 'Gluten-Free', 'Keto', 'Paleo'])]
    public string $diet;

    /**
     * @var Ingredient[] List of ingredients
     */
    public array $ingredients;

    /**
     * @var string[] Cooking instructions
     */
    public array $steps;

    public function toString(): string
    {
        $ingredients = implode(\PHP_EOL, array_map(static fn (Ingredient $ing) => $ing->toString(), $this->ingredients));
        $steps = implode(\PHP_EOL, $this->steps);

        return <<<RECIPE
            Recipe: {$this->name}
            Duration: {$this->duration} minutes
            Level: {$this->level}
            Diet: {$this->diet}
            Ingredients:
            {$ingredients}

            Steps:
            {$steps}
            RECIPE;
    }
}
