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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Validator\Constraints as Assert;

#[AsTool('tool_with_constraints', 'A tool with constraints')]
final class ToolWithConstraints
{
    public function __invoke(Recipe $recipe): string
    {
        return \sprintf('Ingredient: %s', $recipe->ingredient);
    }
}

final class Recipe
{
    public function __construct(
        #[Assert\Choice(choices: ['flour', 'sugar', 'butter'], message: 'The value must be one of {{ choices }}.')]
        public string $ingredient,
    ) {
    }
}
