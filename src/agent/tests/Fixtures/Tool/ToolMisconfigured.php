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

#[AsTool('tool_misconfigured', description: 'This tool is misconfigured, see method', method: 'foo')]
final class ToolMisconfigured
{
    public function bar(): string
    {
        return 'Wrong Config Attribute';
    }
}
