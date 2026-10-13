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

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\SchemaFactoryInterface;

/**
 * Describes the parameters of a tool with #[MapToolArguments] by the properties of the mapped class.
 *
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class MapToolArgumentsSchemaFactory implements SchemaFactoryInterface
{
    public function __construct(
        private readonly SchemaFactoryInterface $inner = new Factory(),
    ) {
    }

    public function buildParameters(string $className, string $methodName, array $context = []): ?array
    {
        if (null !== $mapped = MappedToolArgument::forMethod(new \ReflectionMethod($className, $methodName))) {
            return $this->inner->buildProperties($mapped->className, $context);
        }

        return $this->inner->buildParameters($className, $methodName, $context);
    }

    public function buildProperties(string $className, array $context = []): ?array
    {
        return $this->inner->buildProperties($className, $context);
    }
}
