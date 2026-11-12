<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Mcp;

use Mcp\Capability\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\ParameterSchemaEnricherInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\SchemaAttributeEnricher;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchema;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 *
 * @internal
 */
final class McpSchemaAttributeEnricher implements PropertySchemaEnricherInterface, ParameterSchemaEnricherInterface
{
    public function __construct(
        private readonly SchemaAttributeEnricher $schemaAttributeEnricher = new SchemaAttributeEnricher(),
    ) {
    }

    public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
    {
        return $this->schemaAttributeEnricher->enrich($property, $config, $document);
    }

    public function enrichParameter(\ReflectionParameter $parameter, array $schema): array
    {
        $attributes = $parameter->getAttributes(Schema::class);
        if ([] === $attributes) {
            return $this->schemaAttributeEnricher->enrichParameter($parameter, $schema);
        }

        $mcpSchema = [];
        foreach ($attributes as $attribute) {
            $attribute = $attribute->newInstance();
            $mcpSchema = array_replace($mcpSchema, $attribute->definition ?? $attribute->toArray());
        }

        foreach (['minimum' => 'exclusiveMinimum', 'maximum' => 'exclusiveMaximum'] as $bound => $exclusiveBound) {
            if (!isset($mcpSchema[$exclusiveBound]) || !\is_bool($mcpSchema[$exclusiveBound])) {
                continue;
            }

            if (true === $mcpSchema[$exclusiveBound] && isset($mcpSchema[$bound])) {
                $mcpSchema[$exclusiveBound] = $mcpSchema[$bound];
                unset($mcpSchema[$bound]);
            } else {
                unset($mcpSchema[$exclusiveBound]);
            }
        }

        if (\is_array($schema['type'] ?? null) && \in_array('null', $schema['type'], true)) {
            $mcpSchema['type'] = $schema['type'];
        }

        return array_replace($schema, $mcpSchema, $this->schemaAttributeEnricher->apply($parameter, []));
    }
}
