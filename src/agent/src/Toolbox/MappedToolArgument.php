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

use Symfony\AI\Agent\Toolbox\Attribute\MapToolArguments;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;

/**
 * Resolves an opt-in #[MapToolArguments] method signature for schema generation and argument resolution.
 *
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 *
 * @internal
 */
final class MappedToolArgument
{
    private function __construct(
        public readonly \ReflectionParameter $parameter,
        public readonly string $className,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buildParameters(Factory $factory, string $className, string $methodName): ?array
    {
        $mapped = self::forMethod(new \ReflectionMethod($className, $methodName));
        if (null === $mapped) {
            return $factory->buildParameters($className, $methodName);
        }

        return $factory->buildProperties($mapped->className);
    }

    /**
     * @throws ToolConfigurationException When the attribute is present on an unsupported signature
     */
    public static function forMethod(\ReflectionMethod $method): ?self
    {
        $parameters = $method->getParameters();
        $mappedParameter = null;
        foreach ($parameters as $parameter) {
            if ([] !== $parameter->getAttributes(MapToolArguments::class)) {
                $mappedParameter = $parameter;
                break;
            }
        }

        if (null === $mappedParameter) {
            return null;
        }

        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();
        if (1 !== \count($parameters)) {
            throw ToolConfigurationException::invalidMapToolArguments($className, $methodName, 'the attribute is only valid on a method with exactly one parameter, and that parameter must carry the attribute.');
        }

        $parameter = $mappedParameter;
        if ($parameter->allowsNull()) {
            throw ToolConfigurationException::invalidMapToolArguments($className, $methodName, 'the mapped parameter must not be nullable.');
        }

        $type = $parameter->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName()) || !(new \ReflectionClass($type->getName()))->isInstantiable()) {
            throw ToolConfigurationException::invalidMapToolArguments($className, $methodName, 'the mapped parameter must be a concrete class.');
        }

        return new self($parameter, $type->getName());
    }
}
