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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolConfigurationException extends InvalidArgumentException implements ExceptionInterface
{
    public static function invalidMethod(string $toolClass, string $methodName, \ReflectionException $previous): self
    {
        return new self(\sprintf('Method "%s" not found in tool "%s".', $methodName, $toolClass), previous: $previous);
    }

    public static function invalidMapToolArguments(string $toolClass, string $methodName, string $reason): self
    {
        return new self(\sprintf('Invalid #[MapToolArguments] usage on "%s::%s": %s', $toolClass, $methodName, $reason));
    }
}
