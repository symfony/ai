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

use Symfony\AI\Platform\Contract\JsonSchema\ClassSchemaResolver\DiscriminatorMapSchemaResolver;
use Symfony\AI\Platform\Contract\JsonSchema\DefinitionProcessor\WritablePropertiesProcessor;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\RequiredPropertyEnricher;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\SchemaAttributeEnricher;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\ValidatorConstraintsEnricher;
use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Enricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\NullSyntax;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Factory implements SchemaFactoryInterface
{
    private readonly SchemaGenerator $generator;
    private readonly ShortNameDefinitionPolicy $definitionPolicy;
    private readonly ?SchemaAttributeEnricher $schemaAttributeEnricher;
    private readonly TypeResolverInterface $typeResolver;
    private readonly Dialect $dialect;

    /**
     * @param iterable<PropertySchemaEnricherInterface>|null $propertySchemaEnrichers null for the validator constraints and #[Schema] enrichers
     * @param iterable<ClassSchemaResolverInterface>|null    $classSchemaResolvers    null for the discriminator map and native class resolvers
     * @param iterable<DefinitionProcessorInterface>         $definitionProcessors
     */
    public function __construct(
        ?iterable $propertySchemaEnrichers = null,
        ?iterable $classSchemaResolvers = null,
        iterable $definitionProcessors = [],
        ?PropertyInfoExtractorInterface $propertyInfoExtractor = null,
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?TypeResolverInterface $typeResolver = null,
    ) {
        $classMetadataFactory ??= new ClassMetadataFactory(new AttributeLoader());
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
        $this->dialect = new Dialect('#/$defs/', NullSyntax::Union);
        $this->definitionPolicy = new ShortNameDefinitionPolicy();

        $propertySchemaEnrichers = null === $propertySchemaEnrichers ? self::getDefaultEnrichers() : iterator_to_array($propertySchemaEnrichers, false);
        $classSchemaResolvers = null === $classSchemaResolvers
            ? [new DiscriminatorMapSchemaResolver($classMetadataFactory), new NativeClassSchemaResolver()]
            : iterator_to_array($classSchemaResolvers, false);

        $this->schemaAttributeEnricher = array_find($propertySchemaEnrichers, static fn (PropertySchemaEnricherInterface $enricher): bool => $enricher instanceof SchemaAttributeEnricher);

        $this->generator = new SchemaGenerator(
            $propertyInfoExtractor ?? self::createPropertyInfoExtractor($classMetadataFactory),
            $this->definitionPolicy,
            $classSchemaResolvers,
            propertySchemaEnrichers: [...$propertySchemaEnrichers, new RequiredPropertyEnricher()],
            definitionProcessors: [new WritablePropertiesProcessor(), ...$definitionProcessors],
        );

        foreach ($classSchemaResolvers as $resolver) {
            if ($resolver instanceof DiscriminatorMapSchemaResolver) {
                $resolver->setGenerator($this->generator);
            }
        }
    }

    public function buildParameters(string $className, string $methodName, array $context = []): ?array
    {
        $this->definitionPolicy->reset();
        $config = $this->createConfiguration($context);

        $properties = $required = [];
        foreach ((new \ReflectionMethod($className, $methodName))->getParameters() as $parameter) {
            $schema = $this->generator->generate($this->typeResolver->resolve($parameter), $config)->flatten()->getRoot();

            if ('' !== $description = self::getParameterDescription($parameter)) {
                $schema['description'] = $description;
            }

            $properties[$parameter->name] = self::removeEmptyItems($this->schemaAttributeEnricher?->apply($parameter, $schema) ?? $schema);
            if (!$parameter->isOptional()) {
                $required[] = $parameter->name;
            }
        }

        if ([] === $properties) {
            return null;
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    public function buildProperties(string $className, array $context = []): ?array
    {
        $this->definitionPolicy->reset();
        $schema = $this->generator->generate(Type::object($className), $this->createConfiguration($context))->flatten();
        $root = self::removeEmptyItems($schema->getRoot());

        if ([] !== $schema->getDefinitions()) {
            return [...$root, '$defs' => array_map(self::removeEmptyItems(...), $schema->getDefinitions())];
        }

        return isset($root['properties']) ? $root : null;
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
        return new Configuration(
            dialect: $this->dialect,
            groups: $context['serializer_groups'] ?? [],
            allowExtraAttributes: false,
        );
    }

    /**
     * Untyped array items come back as an empty PHP array, which would be encoded as an invalid JSON list.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function removeEmptyItems(array $schema): array
    {
        if ([] === ($schema['items'] ?? null)) {
            unset($schema['items']);
        }

        foreach ($schema as $keyword => $value) {
            if (\is_array($value) && !\in_array($keyword, ['type', 'enum', 'const', 'required', 'examples'], true)) {
                $schema[$keyword] = array_is_list($value) || \in_array($keyword, ['properties', '$defs'], true)
                    ? array_map(static fn (mixed $item): mixed => \is_array($item) ? self::removeEmptyItems($item) : $item, $value)
                    : self::removeEmptyItems($value);
            }
        }

        return $schema;
    }

    private static function getParameterDescription(\ReflectionParameter $parameter): string
    {
        $comment = $parameter->getDeclaringFunction()->getDocComment();
        if (false === $comment) {
            return '';
        }

        if (preg_match('/@param\s+\S+\s+\$'.preg_quote($parameter->getName(), '/').'\s+((.*)(?=\*)|.*)/', $comment, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
