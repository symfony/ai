<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Loads entities as embeddable documents, so an indexer can walk a table the way it walks a directory.
 *
 * Entities are read page by page and the pages are yielded lazily, so indexing a large table does
 * not depend on how much of it fits into memory at once.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EntityLoader implements LoaderInterface
{
    /**
     * @param class-string|null               $entityClass    the entity to load when `load()` is called without a source
     * @param (\Closure(object): string)|null $contentBuilder builds the text to embed, defaulting to what the entity declares itself
     * @param positive-int                    $batchSize      how many entities are read per query
     * @param bool                            $clearBatches   whether to clear the entity manager after every page
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?string $entityClass = null,
        private readonly ?\Closure $contentBuilder = null,
        private readonly int $batchSize = 100,
        private readonly bool $clearBatches = true,
    ) {
    }

    /**
     * @param string|null                                                                             $source  the entity class to load, defaulting to the one this loader was built with
     * @param array{criteria?: array<string, mixed>, batch_size?: positive-int, clear_batches?: bool} $options
     *
     * @return iterable<EntityDocument>
     */
    public function load(?string $source = null, array $options = []): iterable
    {
        $entityClass = $source ?? $this->entityClass;

        if (null === $entityClass) {
            throw new InvalidArgumentException('No entity class given, pass one to "load()" or to the constructor.');
        }

        if (!class_exists($entityClass)) {
            throw new InvalidArgumentException(\sprintf('Class "%s" does not exist.', $entityClass));
        }

        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $identifierField = $metadata->getSingleIdentifierFieldName();
        $repository = $this->entityManager->getRepository($entityClass);
        $criteria = $options['criteria'] ?? [];
        $batchSize = $options['batch_size'] ?? $this->batchSize;
        $clearBatches = $options['clear_batches'] ?? $this->clearBatches;

        $offset = 0;
        while (true) {
            $entities = $repository->findBy($criteria, [$identifierField => 'ASC'], $batchSize, $offset);

            if ([] === $entities) {
                return;
            }

            foreach ($entities as $entity) {
                yield EntityDocument::fromEntity(
                    entity: $entity,
                    id: $this->identifierOf($metadata->getIdentifierValues($entity)[$identifierField]),
                    content: null === $this->contentBuilder ? null : ($this->contentBuilder)($entity),
                );
            }

            $offset += $batchSize;

            // Every entity read here, and everything its content pulled in behind it, would
            // otherwise stay in the identity map until the whole table has been walked. The
            // documents are already built by now, so nothing downstream needs them managed.
            if ($clearBatches) {
                $this->entityManager->clear();
            }
        }
    }

    private function identifierOf(mixed $identifier): int|string
    {
        if (\is_int($identifier) || \is_string($identifier)) {
            return $identifier;
        }

        if ($identifier instanceof \Stringable) {
            return (string) $identifier;
        }

        throw new InvalidArgumentException(\sprintf('Cannot use a "%s" identifier as a document id, it is neither an int, a string nor stringable.', get_debug_type($identifier)));
    }
}
