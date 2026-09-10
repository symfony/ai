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

#[AsTool('tool_with_invalid_mapped_arguments', 'An invalid mapped tool with extra parameters')]
final class ToolWithInvalidMappedArguments
{
    public function __invoke(
        #[MapToolArguments]
        Recipe $recipe,
        string $extra,
    ): string {
        return $recipe->ingredient.$extra;
    }
}
