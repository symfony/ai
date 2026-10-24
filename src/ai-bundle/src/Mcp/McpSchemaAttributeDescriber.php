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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PropertyDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaAttributeDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 *
 * @internal
 */
final class McpSchemaAttributeDescriber implements PropertyDescriberInterface
{
    public function __construct(
        private readonly SchemaAttributeDescriber $schemaAttributeDescriber = new SchemaAttributeDescriber(),
    ) {
    }

    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        $attributes = [];
        if ($subject->getReflector() instanceof \ReflectionParameter) {
            $attributes = $subject->getAttributes(Schema::class);
        }
        if ([] === $attributes) {
            $this->schemaAttributeDescriber->describeProperty($subject, $schema);

            return;
        }

        $mcpSchema = [];
        foreach ($attributes as $attribute) {
            $mcpSchema = array_replace($mcpSchema, $attribute->definition ?? $attribute->toArray());
        }

        $platformSchema = null;
        $this->schemaAttributeDescriber->describeProperty($subject, $platformSchema);

        $schema = array_replace($schema ?? [], $mcpSchema, $platformSchema ?? []);
    }
}
