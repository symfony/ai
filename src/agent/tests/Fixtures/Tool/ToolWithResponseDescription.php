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

#[AsTool('tool_with_response', 'A tool that searches for items', method: 'search', responseDescription: 'Returns a list of matching items with name and score')]
final class ToolWithResponseDescription
{
    /**
     * @param string $query The search query
     *
     * @return array<string, mixed>
     */
    public function search(string $query): array
    {
        return [];
    }
}
