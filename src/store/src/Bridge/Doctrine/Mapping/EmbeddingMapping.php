<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Mapping;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Where an entity keeps its embedding and what the embedding is built from.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class EmbeddingMapping
{
    /**
     * @param ClassMetadata<object>  $metadata
     * @param non-empty-list<string> $sourceFields
     */
    private function __construct(
        private readonly ClassMetadata $metadata,
        private readonly ?string $vectorField,
        private readonly array $sourceFields,
    ) {
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    public static function fromClassMetadata(ClassMetadata $metadata): ?self
    {
        $classAttribute = null;
        $class = $metadata->getReflectionClass();
        do {
            $classAttribute = ($class->getAttributes(Embedding::class)[0] ?? null)?->newInstance();
        } while (null === $classAttribute && false !== $class = $class->getParentClass());

        $vectorField = null;
        $propertyAttribute = null;
        foreach (self::properties($metadata->getReflectionClass()) as $property) {
            $attribute = $property->getAttributes(Embedding::class)[0] ?? null;

            if (null !== $attribute) {
                $vectorField = $property->getName();
                $propertyAttribute = $attribute->newInstance();

                break;
            }
        }

        if (null !== $classAttribute && null !== $propertyAttribute) {
            throw new InvalidArgumentException(\sprintf('The entity "%s" has #[Embedding] on the class and on "$%s", choose one.', $metadata->getName(), $vectorField));
        }

        $attribute = $classAttribute ?? $propertyAttribute;
        if (null === $attribute) {
            return null;
        }

        $label = null === $vectorField ? $metadata->getName() : \sprintf('%s::$%s', $metadata->getName(), $vectorField);

        if (null !== $vectorField && !$metadata->hasField($vectorField)) {
            throw new InvalidArgumentException(\sprintf('The embedding "%s" is not a mapped field.', $label));
        }

        if ([] === $attribute->from) {
            throw new InvalidArgumentException(\sprintf('The embedding "%s" needs the fields it is built from.', $label));
        }

        foreach ($attribute->from as $sourceField) {
            if (!$metadata->hasField($sourceField)) {
                throw new InvalidArgumentException(\sprintf('The embedding "%s" is built from "%s", which is not a mapped field.', $label, $sourceField));
            }
        }

        return new self($metadata, $vectorField, $attribute->from);
    }

    /**
     * The field holding the vector, null when the entity is mirrored into another store.
     */
    public function getVectorField(): ?string
    {
        return $this->vectorField;
    }

    public function isMirrored(): bool
    {
        return null === $this->vectorField;
    }

    /**
     * @throws InvalidArgumentException when the entity is mirrored into another store
     */
    public function getRequiredVectorField(): string
    {
        return $this->vectorField ?? throw new InvalidArgumentException(\sprintf('Entity "%s" is mirrored into another store, it has no field for the vector.', $this->metadata->getName()));
    }

    public function setVector(EntityManagerInterface $entityManager, object $entity, ?VectorInterface $vector): void
    {
        $this->metadata->setFieldValue($entity, $this->getRequiredVectorField(), $vector);

        // with explicit change tracking, Doctrine only writes entities scheduled for a dirty check
        if ($this->metadata->isChangeTrackingDeferredExplicit() && $entityManager->contains($entity)) {
            $entityManager->getUnitOfWork()->scheduleForDirtyCheck($entity);
        }
    }

    public function getContent(object $entity): string
    {
        if ($entity instanceof IndexableEntityInterface) {
            return $entity->getIndexableContent();
        }

        $lines = [];
        foreach ($this->sourceFields as $sourceField) {
            $value = $this->metadata->getFieldValue($entity, $sourceField);

            foreach (\is_array($value) ? $value : [$value] as $item) {
                $lines[] = $this->toText($item, $sourceField);
            }
        }

        return implode(\PHP_EOL, array_filter($lines, static fn (string $line): bool => '' !== $line));
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    public function isAffectedBy(array $changeSet): bool
    {
        return [] !== array_intersect_key($changeSet, array_flip($this->sourceFields));
    }

    /**
     * @param \ReflectionClass<covariant object> $class
     *
     * @return iterable<\ReflectionProperty>
     */
    private static function properties(\ReflectionClass $class): iterable
    {
        do {
            yield from $class->getProperties();
        } while (false !== $class = $class->getParentClass());
    }

    private function toText(mixed $value, string $sourceField): string
    {
        if (null === $value || \is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_array($value)) {
            $parts = array_map(fn (mixed $part): string => $this->toText($part, $sourceField), $value);

            return implode(', ', array_filter($parts, static fn (string $part): bool => '' !== $part));
        }

        $label = null === $this->vectorField ? $this->metadata->getName() : \sprintf('%s::$%s', $this->metadata->getName(), $this->vectorField);

        throw new InvalidArgumentException(\sprintf('The embedding "%s" is built from "%s", which holds a "%s" that has no text. Implement "%s" to build the text.', $label, $sourceField, get_debug_type($value), IndexableEntityInterface::class));
    }
}
