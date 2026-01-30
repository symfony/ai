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

use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
interface ToolCallArgumentResolverInterface
{
    /**
     * @return array<string, mixed>
     *
     * @throws ToolException When it is not possible to resolve the tool arguments
     */
    public function resolveArguments(Tool $metadata, ToolCall $toolCall): array;
}
