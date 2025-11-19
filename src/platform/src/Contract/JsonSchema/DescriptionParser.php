<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DescriptionParser
{
    public function getDescription(\ReflectionProperty|\ReflectionParameter $reflector): string
    {
        if ($reflector instanceof \ReflectionProperty) {
            return $this->fromProperty($reflector);
        }

        return $this->fromParameter($reflector);
    }

    private function fromProperty(\ReflectionProperty $property): string
    {
        $comment = $property->getDocComment();

        if (\is_string($comment) && preg_match('/@var\s+[a-zA-Z\\\\]+\s+((.*)(?=\*)|.*)/', $comment, $matches)) {
            return trim($matches[1]);
        }

        $class = $property->getDeclaringClass();
        if ($class->hasMethod('__construct')) {
            return $this->fromParameter(
                new \ReflectionParameter([$class->getName(), '__construct'], $property->getName())
            );
        }

        return '';
    }

    private function fromParameter(\ReflectionParameter $parameter): string
    {
        $comment = $parameter->getDeclaringFunction()->getDocComment();
        if (!$comment) {
            return '';
        }

        if (preg_match('/@param\s+\S+\s+\$'.preg_quote($parameter->getName(), '/').'\s+((.*)(?=\*)|.*)/', $comment, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
