<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Fixture\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

final class ToolOnMethodConflicting
{
    #[AsTool('tool_conflicting', 'A tool pointing to another method', method: 'other')]
    public function current(): string
    {
        return 'current';
    }

    public function other(): string
    {
        return 'other';
    }
}
