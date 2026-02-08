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

use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;

final class MethodDescriber implements ObjectDescriberInterface, PropertyDescriberInterface
{
    public function describeObject(ObjectSubject $subject, ?array &$schema): iterable
    {
        $reflection = $subject->getReflector();

        if (!$reflection instanceof \ReflectionMethod) {
            return [];
        }

        foreach ($reflection->getParameters() as $reflector) {
            yield new PropertySubject($reflector->name, $reflector);
        }
    }

    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        $reflector = $subject->getReflector();
        if (!$reflector instanceof \ReflectionParameter) {
            return;
        }

        if (!$description = $this->fromParameter($reflector)) {
            return;
        }

        $schema['description'] = $description;
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
