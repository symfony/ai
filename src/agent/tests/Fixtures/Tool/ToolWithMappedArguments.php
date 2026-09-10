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
use Symfony\AI\Agent\Toolbox\Attribute\MapToolArguments;

#[AsTool('tool_with_mapped_arguments', 'A tool that maps a flat payload onto a DTO')]
final class ToolWithMappedArguments
{
    public function __invoke(
        #[MapToolArguments]
        MappedRecipe $recipe,
    ): string {
        return \sprintf('Ingredient: %s', $recipe->ingredient);
    }
}
