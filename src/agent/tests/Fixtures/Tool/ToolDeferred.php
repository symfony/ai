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

#[AsTool('tool_deferred', 'A tool that is only available through a tool search', deferred: true)]
final class ToolDeferred
{
    public function __invoke(): string
    {
        return 'Hello world!';
    }
}
