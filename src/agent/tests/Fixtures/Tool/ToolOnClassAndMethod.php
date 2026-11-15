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

#[AsTool('tool_on_class', 'A tool declared on the class', method: 'onClass')]
final class ToolOnClassAndMethod
{
    #[AsTool('tool_on_method', 'A tool declared on the method')]
    public function onMethod(): string
    {
        return 'method';
    }

    public function onClass(): string
    {
        return 'class';
    }
}
