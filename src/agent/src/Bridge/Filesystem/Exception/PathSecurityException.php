<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Filesystem\Exception;

use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class PathSecurityException extends RuntimeException implements ExceptionInterface, ToolExecutionExceptionInterface
{
    public function getToolCallResult(): string
    {
        return $this->getMessage();
    }
}
