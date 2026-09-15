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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolConfigurationException extends InvalidArgumentException implements ExceptionInterface
{
    public static function invalidMethod(string $toolClass, string $methodName, \ReflectionException $previous): self
    {
        return new self(\sprintf('Method "%s" not found in tool "%s".', $methodName, $toolClass), previous: $previous);
    }

    public static function duplicateToolName(string $name, ToolboxInterface $firstToolbox, Tool $firstTool, ToolboxInterface $secondToolbox, Tool $secondTool): self
    {
        return new self(\sprintf(
            'Tool "%s" is offered by more than one toolbox: "%s" (%s::%s) and "%s" (%s::%s). Tool names must be unique across the toolboxes of a chain.',
            $name,
            get_debug_type($firstToolbox),
            $firstTool->getReference()->getClass(),
            $firstTool->getReference()->getMethod(),
            get_debug_type($secondToolbox),
            $secondTool->getReference()->getClass(),
            $secondTool->getReference()->getMethod(),
        ));
    }
}
