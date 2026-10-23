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

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
interface HasToolCallInterface
{
    public function setToolCall(?ToolCall $toolCall): void;

    public function getToolCall(): ?ToolCall;
}
