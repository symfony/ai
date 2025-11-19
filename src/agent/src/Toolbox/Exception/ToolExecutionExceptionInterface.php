<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Exception;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
interface ToolExecutionExceptionInterface extends ExceptionInterface
{
    public function getToolCallResult(): mixed;
}
