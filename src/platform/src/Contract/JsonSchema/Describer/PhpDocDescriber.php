<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PhpDocDescriber implements DescriberInterface
{
    public function describe(\ReflectionProperty|\ReflectionParameter|\ReflectionClass $reflector, ?array &$schema): void
    {
        $description = match (true) {
            $reflector instanceof \ReflectionProperty => $this->fromProperty($reflector),
            $reflector instanceof \ReflectionParameter => $this->fromParameter($reflector),
            $reflector instanceof \ReflectionClass => '',
        };

        if (!$description) {
            return;
        }

        $schema['description'] = $description;
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
