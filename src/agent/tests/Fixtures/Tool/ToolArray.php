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

#[AsTool('tool_array', 'A tool with array parameters')]
final class ToolArray
{
    /**
     * @param string[]  $urls
     * @param list<int> $ids
     */
    public function __invoke(array $urls, array $ids): string
    {
        return 'Hello world!';
    }
}
