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
use Symfony\AI\Agent\Toolbox\Exception\ToolErrorException;

#[AsTool('tool_error', description: 'This tool tells the model why it failed', method: 'bar')]
final class ToolError
{
    public function bar(string $id): string
    {
        throw new ToolErrorException(\sprintf('There is no order "%s".', $id));
    }
}
