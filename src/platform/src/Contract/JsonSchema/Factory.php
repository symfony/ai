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

use Symfony\AI\Platform\Contract\JsonSchema\Enricher\RequiredPropertyEnricher;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\SchemaAttributeEnricher;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\ValidatorConstraintsEnricher;
use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\JsonSchema\NullSyntax;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\Schema;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
 *         minimum?: int|float,
 *         maximum?: int|float,
 *         multipleOf?: int|float,
 *         exclusiveMinimum?: int|float,
 *         exclusiveMaximum?: int|float,
 *         minItems?: int,
 *         maxItems?: int,
 *         uniqueItems?: bool,
 *         minContains?: int,
 *         maxContains?: int,
 *         required?: bool|list<string>,
 *         minProperties?: int,
 *         maxProperties?: int,
 *         dependentRequired?: bool,
 *         anyOf?: list<mixed>,
 *         items?: array<string, mixed>,
 *         properties?: array<string, array<string, mixed>>,
 *         additionalProperties?: bool|array<string, mixed>,
 *     }>,
 *     required: list<string>,
 *     additionalProperties: false,
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Factory implements SchemaFactoryInterface
{
    private readonly SchemaGenerator $generator;
    private readonly ?SchemaAttributeEnricher $schemaAttributeEnricher;
    private readonly Configuration $config;

    /**
     * @param iterable<PropertySchemaEnricherInterface>|null $propertySchemaEnrichers null for the validator constraints and #[Schema] enrichers
     * @param iterable<ClassSchemaResolverInterface>|null    $classSchemaResolvers    null for the native class resolver
     * @param iterable<DefinitionProcessorInterface>|null    $definitionProcessors    null for none
     */
    public function __construct(
        ?iterable $propertySchemaEnrichers = null,
        ?iterable $classSchemaResolvers = null,
        ?iterable $definitionProcessors = null,
        ?PropertyInfoExtractorInterface $propertyInfoExtractor = null,
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?TypeResolverInterface $typeResolver = null,
    ) {
        $classMetadataFactory ??= new ClassMetadataFactory(new AttributeLoader());

        $propertySchemaEnrichers = null === $propertySchemaEnrichers ? self::getDefaultEnrichers() : iterator_to_array($propertySchemaEnrichers, false);
        $this->schemaAttributeEnricher = array_find($propertySchemaEnrichers, static fn (PropertySchemaEnricherInterface $enricher): bool => $enricher instanceof SchemaAttributeEnricher);

        $this->generator = new SchemaGenerator(
            $propertyInfoExtractor ?? self::createPropertyInfoExtractor($classMetadataFactory),
            new ShortNameDefinitionPolicy(),
            $classSchemaResolvers ?? [new NativeClassSchemaResolver()],
            propertySchemaEnrichers: [...$propertySchemaEnrichers, new RequiredPropertyEnricher()],
            definitionProcessors: $definitionProcessors ?? [],
            classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata($classMetadataFactory),
            typeResolver: $typeResolver,
        );
        $this->config = new Configuration(new Dialect('#/$defs/', NullSyntax::Union), allowExtraAttributes: false, direction: Direction::Input);
    }

    public function buildParameters(string $className, string $methodName, array $context = []): ?array
    {
        $parameters = (new \ReflectionMethod($className, $methodName))->getParameters();
        $schema = $this->generator->generateForParameters($parameters, $this->createConfiguration($context))->flatten();
        $root = self::normalize($schema->getRoot());

        if (!isset($root['properties'])) {
            return null;
        }

        foreach ($parameters as $parameter) {
            if (!isset($root['properties'][$parameter->name])) {
                continue;
            }

            $property = $root['properties'][$parameter->name];
            unset($property['default']);
            $root['properties'][$parameter->name] = $this->schemaAttributeEnricher?->apply($parameter, $property) ?? $property;
        }

        return self::withDefinitions(['type' => 'object', 'properties' => $root['properties'], 'required' => $root['required'] ?? [], 'additionalProperties' => false], $schema);
    }

    public function buildProperties(string $className, array $context = []): ?array
    {
        $schema = $this->generator->generate(Type::object($className), $this->createConfiguration($context))->flatten();
        $root = self::normalize($schema->getRoot());

        if ([] === $schema->getDefinitions() && !isset($root['properties'])) {
            return null;
        }

        return self::withDefinitions($root, $schema);
    }

    /**
     * @return list<PropertySchemaEnricherInterface>
     */
    private static function getDefaultEnrichers(): array
    {
        $enrichers = [];
        if (interface_exists(ValidatorInterface::class)) {
            $enrichers[] = new ValidatorConstraintsEnricher();
        }
        $enrichers[] = new SchemaAttributeEnricher();

        return $enrichers;
    }

    private static function createPropertyInfoExtractor(ClassMetadataFactoryInterface $classMetadataFactory): PropertyInfoExtractorInterface
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpStanExtractor = new PhpStanExtractor();

        return new PropertyInfoExtractor(
            [new SerializerExtractor($classMetadataFactory), $reflectionExtractor],
            [$phpStanExtractor, $reflectionExtractor],
            [$phpStanExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createConfiguration(array $context): Configuration
    {
        if ([] === $groups = $context['serializer_groups'] ?? []) {
            return $this->config;
        }

        return new Configuration($this->config->dialect, $groups, allowExtraAttributes: false, direction: Direction::Input);
    }

    /**
     * @param array<string, mixed> $root
     *
     * @return array<string, mixed>
     */
    private static function withDefinitions(array $root, Schema $schema): array
    {
        if ([] === $definitions = $schema->getDefinitions()) {
            return $root;
        }

        return [...$root, '$defs' => array_map(self::normalize(...), $definitions)];
    }

    /**
     * Unwraps the empty schemas into arrays and drops the empty items and additionalProperties schemas, which allow any value.
     *
     * @param array<string, mixed>|\ArrayObject<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function normalize(array|\ArrayObject $schema): array
    {
        $schema = (array) $schema;

        foreach ($schema as $keyword => $value) {
            if ($value instanceof \ArrayObject) {
                $schema[$keyword] = $value = (array) $value;
            }

            if (\in_array($keyword, ['items', 'additionalProperties'], true) && [] === $value) {
                unset($schema[$keyword]);

                continue;
            }

            if (\is_array($value) && !\in_array($keyword, ['type', 'enum', 'const', 'required', 'examples'], true)) {
                $schema[$keyword] = array_is_list($value) || \in_array($keyword, ['properties', '$defs'], true)
                    ? array_map(static fn (mixed $item): mixed => \is_array($item) || $item instanceof \ArrayObject ? self::normalize($item) : $item, $value)
                    : self::normalize($value);
            }
        }

        return $schema;
    }
}
