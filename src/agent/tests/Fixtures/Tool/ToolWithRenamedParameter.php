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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

#[AsTool('tool_with_renamed_parameter', 'A tool which renames a parameter with #[Schema(name: ...)]')]
final class ToolWithRenamedParameter
{
    /**
     * @param string $searchTerm The term to search for
     */
    public function __invoke(
        #[Schema(name: 'search_term')]
        string $searchTerm,
    ): string {
        return $searchTerm;
    }
}
