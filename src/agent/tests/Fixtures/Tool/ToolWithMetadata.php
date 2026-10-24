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
use Symfony\AI\Platform\Tool\ToolEffectHints;

#[AsTool('tool_with_metadata', 'A tool carrying metadata', metadata: ['needs_confirmation' => true, 'category' => 'admin'], effectHints: new ToolEffectHints(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false))]
final class ToolWithMetadata
{
    public function __invoke(): string
    {
        return 'Hello world!';
    }
}
