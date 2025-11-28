<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate\Exception;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    public static function invalidVariableName(string $type): self
    {
        return new self(\sprintf('Variable name must be a string, "%s" given.', $type));
    }

    public static function invalidVariableValue(string $variableName, string $type): self
    {
        return new self(\sprintf('Variable "%s" must be a string, numeric, or Stringable, "%s" given.', $variableName, $type));
    }
}
