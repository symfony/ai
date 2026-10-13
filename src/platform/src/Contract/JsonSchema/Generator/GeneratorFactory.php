<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Generator;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Contract\JsonSchema\SchemaFactoryInterface;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Enricher\ValidatorPropertySchemaEnricher;
use Symfony\Component\JsonSchema\NullSyntax;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;
use Symfony\Component\Validator\Validation;

/**
 * Drop-in for {@see Factory}, built on symfony/json-schema.
 *
 * @phpstan-import-type JsonSchema from Factory
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GeneratorFactory implements SchemaFactoryInterface
{
    private readonly SchemaGenerator $generator;
    private readonly ShortNameDefinitionPolicy $definitionPolicy;
    private readonly SchemaAttributeEnricher $schemaAttributeEnricher;
    private readonly Dialect $dialect;
    private readonly TypeResolverInterface $typeResolver;

    /**
     * @param iterable<string, SchemaProviderInterface> $providers
     */
    public function __construct(
        iterable $providers = [],
        ?TypeResolverInterface $typeResolver = null,
    ) {
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
        $reflectionExtractor = new ReflectionExtractor();
        $phpStanExtractor = new PhpStanExtractor();
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $propertyInfo = new PropertyInfoExtractor(
            [new SerializerExtractor($classMetadataFactory), $reflectionExtractor],
            [$phpStanExtractor, $reflectionExtractor],
            [$phpStanExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor],
        );

        $this->schemaAttributeEnricher = new SchemaAttributeEnricher($providers);
        $enrichers = [];
        if (class_exists(Validation::class)) {
            $enrichers[] = new ValidatorPropertySchemaEnricher(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        }
        $enrichers[] = $this->schemaAttributeEnricher;
        $enrichers[] = new RequiredPropertyEnricher();

        $discriminatorResolver = new DiscriminatorMapSchemaResolver($classMetadataFactory);
        $this->definitionPolicy = new ShortNameDefinitionPolicy();
        $this->generator = new SchemaGenerator(
            $propertyInfo,
            $this->definitionPolicy,
            [$discriminatorResolver, new NativeClassSchemaResolver()],
            propertySchemaEnrichers: $enrichers,
            definitionProcessors: [new WritablePropertiesProcessor()],
            nameConverter: new MetadataAwareNameConverter($classMetadataFactory),
        );
        $discriminatorResolver->setGenerator($this->generator);

        $this->dialect = new Dialect('#/$defs/', NullSyntax::Union);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return JsonSchema|null
     */
    public function buildParameters(string $className, string $methodName, array $context = []): ?array
    {
        $this->definitionPolicy->reset();
        $config = $this->createConfiguration($context);
        $method = new \ReflectionMethod($className, $methodName);

        $properties = $required = [];
        foreach ($method->getParameters() as $parameter) {
            $schema = $this->generator->generate($this->typeResolver->resolve($parameter), $config)->flatten()->getRoot();

            if ('' !== $description = $this->getParameterDescription($parameter)) {
                $schema['description'] = $description;
            }

            $properties[$parameter->name] = $this->schemaAttributeEnricher->apply($parameter, $schema);
            if (!$parameter->isOptional()) {
                $required[] = $parameter->name;
            }
        }

        if ([] === $properties) {
            return null;
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return JsonSchema|null
     */
    public function buildProperties(string $className, array $context = []): ?array
    {
        $this->definitionPolicy->reset();
        $schema = $this->generator->generate(Type::object($className), $this->createConfiguration($context))->flatten();

        if ([] !== $schema->getDefinitions()) {
            // Recursive classes keep their definitions, models expect them next to the root.
            return [...$schema->getRoot(), '$defs' => $schema->getDefinitions()];
        }

        $root = $schema->getRoot();

        return isset($root['properties']) ? $root : null;
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

    private function getParameterDescription(\ReflectionParameter $parameter): string
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
