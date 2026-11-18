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

use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;

final class MethodDescriber implements ObjectDescriberInterface, PropertyDescriberInterface
{
    private readonly DocBlockFactoryInterface $docBlockFactory;

    public function __construct()
    {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

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

        try {
            $docBlock = $this->docBlockFactory->create($comment);
        } catch (\InvalidArgumentException|\RuntimeException) {
            return '';
        }

        foreach ($docBlock->getTagsByName('param') as $tag) {
            if ($tag instanceof Param && $parameter->getName() === $tag->getVariableName()) {
                return preg_replace('/(?<=\S)[ \t]*\R[ \t]*(?=\S)/', ' ', trim((string) $tag->getDescription())) ?? '';
            }
        }

        return '';
    }
}
