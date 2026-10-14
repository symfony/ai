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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Exception\IOException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchema;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;

/**
 * Applies the #[Schema] attribute of a property, its promoted constructor argument or its setter argument.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SchemaAttributeEnricher implements PropertySchemaEnricherInterface
{
    /**
     * @var array<string, SchemaProviderInterface>
     */
    private readonly array $providers;

    /**
     * @param iterable<string, SchemaProviderInterface> $providers
     */
    public function __construct(
        iterable $providers = [],
        private readonly ReflectionExtractor $reflectionExtractor = new ReflectionExtractor(),
    ) {
        $this->providers = $providers instanceof \Traversable ? iterator_to_array($providers) : $providers;
    }

    public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
    {
        foreach ($this->getReflectors($property->class, $property->property) as $reflector) {
            $schema = $this->apply($reflector, $property->schema);
            if ($schema !== $property->schema) {
                return $property->withSchema($schema);
            }
        }

        return $property;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function apply(\ReflectionProperty|\ReflectionParameter $reflector, array $schema): array
    {
        foreach ($reflector->getAttributes(Schema::class) as $reflectionAttribute) {
            $attribute = $reflectionAttribute->newInstance();

            if ($attribute->ref) {
                try {
                    $attributeSchema = json_decode((string) file_get_contents($attribute->ref), true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new IOException(\sprintf('Failed to load the schema from "%s"', $attribute->ref), 0, $e);
                }
            } else {
                $attributeSchema = array_filter((array) $attribute, static fn ($value) => null !== $value && [] !== $value);
                unset($attributeSchema['provider'], $attributeSchema['context']);
            }

            $schema = array_replace_recursive($schema, $attributeSchema);

            if (null !== $attribute->provider) {
                if (!isset($this->providers[$attribute->provider])) {
                    throw new RuntimeException(\sprintf('Schema provider "%s" is not registered.', $attribute->provider));
                }

                $fragment = $this->providers[$attribute->provider]->getSchemaFragment($attribute->context);
                $schema = array_replace_recursive(array_diff_key($schema, $fragment), $fragment);
            }
        }

        return $schema;
    }

    /**
     * @param class-string $class
     *
     * @return iterable<\ReflectionProperty|\ReflectionParameter>
     */
    private function getReflectors(string $class, string $property): iterable
    {
        if (property_exists($class, $property)) {
            yield new \ReflectionProperty($class, $property);
        }

        $writeInfo = $this->reflectionExtractor->getWriteInfo($class, $property);

        yield from match ($writeInfo?->getType()) {
            PropertyWriteInfo::TYPE_METHOD => [new \ReflectionParameter([$class, $writeInfo->getName()], 0)],
            PropertyWriteInfo::TYPE_CONSTRUCTOR => [new \ReflectionParameter([$class, '__construct'], $writeInfo->getName())],
            default => [],
        };
    }
}
