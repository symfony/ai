<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Enricher;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchema;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * Requires every property but optional constructor arguments, and drops defaults, as models fill in every field.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RequiredPropertyEnricher implements PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
    {
        $schema = $property->schema;
        unset($schema['default']);

        return $property->withSchema($schema)->withRequired($this->isRequired($property->class, $property->property));
    }

    /**
     * @param class-string $class
     */
    private function isRequired(string $class, string $property): bool
    {
        foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->name === $property) {
                return !$parameter->isOptional();
            }
        }

        return true;
    }
}
