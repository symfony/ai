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

use Symfony\AI\Platform\Contract\JsonSchema\Describer\DescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PhpDocDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SerializerDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\TypeInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\WithAttributeDescriber;

/**
 * @phpstan-type JsonSchema array{
 *     type: 'object',
 *     properties: array<string, array{
 *         type: string,
 *         description: string,
 *         enum?: list<string>,
 *         const?: string|int|list<string>,
 *         pattern?: string,
 *         minLength?: int,
 *         maxLength?: int,
 *         minimum?: int,
 *         maximum?: int,
 *         multipleOf?: int,
 *         exclusiveMinimum?: int,
 *         exclusiveMaximum?: int,
 *         minItems?: int,
 *         maxItems?: int,
 *         uniqueItems?: bool,
 *         minContains?: int,
 *         maxContains?: int,
 *         required?: bool,
 *         minProperties?: int,
 *         maxProperties?: int,
 *         dependentRequired?: bool,
 *         anyOf?: list<mixed>,
 *     }>,
 *     required: list<string>,
 *     additionalProperties: false,
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Factory
{
    public function __construct(
        /** @var iterable<DescriberInterface> */
        private readonly iterable $describers = [
            new TypeInfoDescriber(),
            new SerializerDescriber(),
            new PhpDocDescriber(),
            new WithAttributeDescriber(),
        ],
    ) {
        foreach ($this->describers as $describer) {
            if ($describer instanceof FactoryAwareInterface) {
                $describer->setFactory($this);
            }
        }
    }

    /**
     * @return JsonSchema|null
     */
    public function buildParameters(string $className, string $methodName): ?array
    {
        $reflection = new \ReflectionMethod($className, $methodName);
        /** @var JsonSchema $schema */
        $schema = [
            'type' => 'object',
        ];

        $required = [];
        foreach ($reflection->getParameters() as $reflector) {
            foreach ($this->describers as $describer) {
                $describer->describe($reflector, $schema['properties'][$reflector->name]);
            }

            if (!$reflector->isOptional()) {
                $required[] = $reflector->name;
            }
        }

        if ($schema['properties'] ?? false) {
            $schema['required'] = $required;
            $schema['additionalProperties'] = false;
        }

        if (['type' => 'object'] === $schema) {
            return null;
        }

        return $schema;
    }

    /**
     * @return JsonSchema|null
     */
    public function buildProperties(string $className): ?array
    {
        $schema = $required = [];
        $classReflector = new \ReflectionClass($className);
        foreach ($this->describers as $describer) {
            $describer->describe($classReflector, $schema);
            foreach ($classReflector->getProperties() as $reflector) {
                $describer->describe($reflector, $schema['properties'][$reflector->name]);

                $required[$reflector->name] = true;
            }
        }

        if ($schema['properties'] ?? false) {
            $schema['required'] = array_keys($required);
            $schema['additionalProperties'] = false;
        }

        if (['type' => 'object'] === $schema) {
            return null;
        }

        return $schema;
    }
}
