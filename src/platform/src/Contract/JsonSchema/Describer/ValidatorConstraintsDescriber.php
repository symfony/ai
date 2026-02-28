<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class ValidatorConstraintsDescriber implements DescriberInterface
{
    private readonly ValidatorInterface $validator;

    public function __construct(
        ?ValidatorInterface $validator = null,
    ) {
        $this->validator = $validator ?? Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function describe(\ReflectionProperty|\ReflectionParameter|\ReflectionClass $reflector, ?array &$schema): void
    {
        if (!$reflector instanceof \ReflectionProperty) {
            return;
        }

        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->validator->getMetadataFor($reflector->class);
        $propertyMetadata = $classMetadata->getPropertyMetadata($reflector->name);

        foreach ($propertyMetadata as $metadata) {
            foreach ($metadata->getConstraints() as $constraint) {
                match (true) {
                    $constraint instanceof Assert\NotNull => $schema['nullable'] = false,
                    $constraint instanceof Assert\NotBlank => $this->describeNotBlank($schema, $constraint),
                    $constraint instanceof Assert\Blank => $this->describeBlank($schema),
                    $constraint instanceof Assert\NotEqualTo, $constraint instanceof Assert\NotIdenticalTo => $this->describeNotEqualTo($schema, $constraint),
                    $constraint instanceof Assert\EqualTo, $constraint instanceof Assert\IdenticalTo => $this->describeEqualTo($schema, $constraint),
                    $constraint instanceof Assert\GreaterThan => $this->describeLowerBound($schema, $constraint, true),
                    $constraint instanceof Assert\GreaterThanOrEqual => $this->describeLowerBound($schema, $constraint, false),
                    $constraint instanceof Assert\LessThan => $this->describeUpperBound($schema, $constraint, true),
                    $constraint instanceof Assert\LessThanOrEqual => $this->describeUpperBound($schema, $constraint, false),
                    $constraint instanceof Assert\Range => $this->describeRange($schema, $constraint),
                    $constraint instanceof Assert\DivisibleBy => $this->describeDivisibleBy($schema, $constraint),
                    $constraint instanceof Assert\Regex => $this->describeRegex($schema, $constraint),
                    $constraint instanceof Assert\Choice => $this->describeChoice($schema, $constraint, $reflector->class),
                    $constraint instanceof Assert\Count => $this->describeCount($schema, $constraint),
                    $constraint instanceof Assert\Length => $this->describeLength($schema, $constraint),
                    $constraint instanceof Assert\Unique => $schema['uniqueItems'] = true,
                    $constraint instanceof Assert\Email => $schema['format'] = 'email',
                    $constraint instanceof Assert\Url => $schema['format'] = 'uri',
                    $constraint instanceof Assert\Date => $schema['format'] = 'date',
                    $constraint instanceof Assert\DateTime => $schema['format'] = 'date-time',
                    $constraint instanceof Assert\Time => $this->describeTime($schema, $constraint),
                    $constraint instanceof Assert\Uuid => $schema['format'] = 'uuid',
                    $constraint instanceof Assert\Ulid => $this->describeUlid($schema, $constraint),
                    $constraint instanceof Assert\Ip => $this->describeIp($schema, $constraint),
                    $constraint instanceof Assert\Hostname => $schema['format'] = 'hostname',
                    $constraint instanceof Assert\IsTrue => $schema['const'] = true,
                    $constraint instanceof Assert\IsFalse => $schema['const'] = false,
                    $constraint instanceof Assert\IsNull => $this->describeIsNull($schema),
                    $constraint instanceof Assert\Type => $this->describeType($schema, $constraint),
                    default => null,
                };
            }
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     *
     * @param-out JsonSchema|array<mixed> $schema
     */
    private function describeRegex(?array &$schema, Assert\Regex $constraint): void
    {
        $schema['pattern'] = $constraint->getHtmlPattern();
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     *
     * @param-out JsonSchema|array<mixed> $schema
     */
    private function describeNotBlank(?array &$schema, Assert\NotBlank $constraint): void
    {
        $schema['nullable'] = $constraint->allowNull;

        if ($this->containsType($schema, 'string')) {
            $schema['minLength'] = 1;
        }

        if ($this->containsType($schema, 'object')) {
            $schema['minProperties'] = 1;
        }

        if ($this->containsType($schema, 'array')) {
            $schema['minItems'] = 1;
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     *
     * @param-out JsonSchema|array<mixed> $schema
     */
    private function describeBlank(?array &$schema): void
    {
        $schema['nullable'] = true;

        if ($this->containsType($schema, 'string')) {
            $schema['maxLength'] = 0;
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeNotEqualTo(?array &$schema, Assert\NotEqualTo|Assert\NotIdenticalTo $constraint): void
    {
        if ($constraint->propertyPath) {
            return;
        }

        $schema['not']['enum'][] = $constraint->value;
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeEqualTo(?array &$schema, Assert\EqualTo|Assert\IdenticalTo $constraint): void
    {
        if ($constraint->propertyPath) {
            return;
        }

        $schema['enum'][] = $constraint->value;
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeChoice(?array &$schema, Assert\Choice $constraint, string $class): void
    {
        if ($constraint->callback) {
            if (\is_callable($choices = [$class, $constraint->callback]) || \is_callable($choices = $constraint->callback)) {
                $choices = $choices();
            }
        } else {
            $choices = $constraint->choices;
        }

        if (null === $choices) {
            return;
        }

        if ($constraint->multiple) {
            $schema['items']['enum'] = $choices;
            if (null !== $constraint->min) {
                $schema['minItems'] = $constraint->min;
            }
            if (null !== $constraint->max) {
                $schema['maxItems'] = $constraint->max;
            }
        } else {
            if ($constraint->match) {
                $schema['enum'] = $choices;
            } else {
                $schema['not']['enum'] = $choices;
            }
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeLowerBound(?array &$schema, Assert\AbstractComparison $constraint, bool $exclusive): void
    {
        if (null !== $constraint->propertyPath || !\is_scalar($constraint->value)) {
            return;
        }

        if (!is_numeric($constraint->value)) {
            $this->appendDescription('Minimum value: '.$constraint->value, $schema);

            return;
        }

        $schema['minimum'] = $constraint->value;
        if ($exclusive) {
            $schema['exclusiveMinimum'] = true;
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeUpperBound(?array &$schema, Assert\AbstractComparison $constraint, bool $exclusive): void
    {
        if (null !== $constraint->propertyPath || !\is_scalar($constraint->value)) {
            return;
        }

        if (!is_numeric($constraint->value)) {
            $this->appendDescription('Maximum value: '.$constraint->value, $schema);

            return;
        }

        $schema['maximum'] = $constraint->value;
        if ($exclusive) {
            $schema['exclusiveMaximum'] = true;
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeRange(?array &$schema, Assert\Range $constraint): void
    {
        if (null === $constraint->minPropertyPath && \is_scalar($constraint->min)) {
            if (is_numeric($constraint->min)) {
                $schema['minimum'] = $constraint->min;
            } else {
                $this->appendDescription('Minimum value: '.$constraint->min, $schema);
            }
        }

        if (null === $constraint->maxPropertyPath && \is_scalar($constraint->max)) {
            if (is_numeric($constraint->min)) {
                $schema['maximum'] = $constraint->max;
            } else {
                $this->appendDescription('Maximum value: '.$constraint->max, $schema);
            }
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeDivisibleBy(?array &$schema, Assert\DivisibleBy $constraint): void
    {
        if (null !== $constraint->propertyPath || !is_numeric($constraint->value)) {
            return;
        }

        $schema['multipleOf'] = $constraint->value;
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeCount(?array &$schema, Assert\Count $constraint): void
    {
        if (null !== $constraint->min) {
            $schema['minItems'] = $constraint->min;
        }

        if (null !== $constraint->max) {
            $schema['maxItems'] = $constraint->max;
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeLength(?array &$schema, Assert\Length $constraint): void
    {
        if (null !== $constraint->min) {
            $schema['minLength'] = $constraint->min;
        }

        if (null !== $constraint->max) {
            $schema['maxLength'] = $constraint->max;
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     *
     * @param-out JsonSchema|array<mixed> $schema
     */
    private function describeTime(?array &$schema, Assert\Time $constraint): void
    {
        if ($constraint->withSeconds) {
            $schema['format'] = 'time';

            return;
        }

        $schema['pattern'] = '^([01]\d|2[0-3]):[0-5]\d$';
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeUlid(?array &$schema, Assert\Ulid $constraint): void
    {
        match ($constraint->format) {
            Assert\Ulid::FORMAT_BASE_32 => $schema['pattern'] = '^[0-7][0-9A-HJKMNP-TV-Z]{25}$',
            Assert\Ulid::FORMAT_BASE_58 => $schema['pattern'] = '^[1-9A-HJ-NP-Za-km-z]{22}$',
            Assert\Ulid::FORMAT_RFC_4122 => $schema['format'] = 'uuid',
            default => null,
        };
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeIp(?array &$schema, Assert\Ip $constraint): void
    {
        if (str_starts_with($constraint->version, Assert\Ip::V4)) {
            $schema['format'] = 'ipv4';

            return;
        }

        if (str_starts_with($constraint->version, Assert\Ip::V6)) {
            $schema['format'] = 'ipv6';
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     *
     * @param-out JsonSchema|array<mixed> $schema
     */
    private function describeIsNull(?array &$schema): void
    {
        $schema['const'] = null;
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function describeType(?array &$schema, Assert\Type $constraint): void
    {
        $constraintTypes = \is_array($constraint->type) ? $constraint->type : [$constraint->type];
        $jsonSchemaTypes = [];

        foreach ($constraintTypes as $constraintType) {
            $jsonSchemaType = $this->mapConstraintTypeToJsonSchemaType($constraintType);

            if (null !== $jsonSchemaType) {
                $jsonSchemaTypes[] = $jsonSchemaType;
            }
        }

        $jsonSchemaTypes = array_values(array_unique($jsonSchemaTypes));
        if ([] === $jsonSchemaTypes) {
            return;
        }

        if (!isset($schema['type'])) {
            $schema['type'] = 1 === \count($jsonSchemaTypes) ? $jsonSchemaTypes[0] : $jsonSchemaTypes;

            return;
        }

        $existingTypes = \is_array($schema['type']) ? $schema['type'] : [$schema['type']];
        $intersectedTypes = array_values(array_intersect($existingTypes, $jsonSchemaTypes));

        if ([] !== $intersectedTypes) {
            $schema['type'] = 1 === \count($intersectedTypes) ? $intersectedTypes[0] : $intersectedTypes;
        }
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     */
    private function containsType(?array $schema, string $type): bool
    {
        if (!isset($schema['type'])) {
            return false;
        }

        $types = \is_array($schema['type']) ? $schema['type'] : [$schema['type']];

        return \in_array($type, $types, true);
    }

    private function mapConstraintTypeToJsonSchemaType(string $constraintType): ?string
    {
        return match ($constraintType) {
            'int', 'integer' => 'integer',
            'float', 'double', 'real', 'number', 'numeric' => 'number',
            'bool', 'boolean' => 'boolean',
            'array', 'list' => 'array',
            'object' => 'object',
            'string' => 'string',
            'null' => 'null',
            default => null,
        };
    }

    /**
     * @param JsonSchema|array<mixed>|null $schema
     *
     * @param-out JsonSchema|array<mixed> $schema
     */
    private function appendDescription(string $description, ?array &$schema): void
    {
        $schema['description'] ??= '';
        if ($schema['description']) {
            $schema['description'] .= "\n";
        }

        $schema['description'] .= $description;
    }
}
