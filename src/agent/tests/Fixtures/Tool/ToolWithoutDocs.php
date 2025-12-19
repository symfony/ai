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

#[AsTool('tool_without_docs', 'A tool with required parameters', method: 'bar')]
final class ToolWithoutDocs
{
    public function bar(string $text, int $number): string
    {
        return \sprintf('%s says "%d".', $text, $number);
    }
}
