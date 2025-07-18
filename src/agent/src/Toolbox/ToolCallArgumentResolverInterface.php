<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
interface ToolCallArgumentResolverInterface
{
    /**
     * @return array<string, mixed>
     */
    public function resolveArguments(object $tool, Tool $metadata, ToolCall $toolCall): array;
}
