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
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Sets the embedding field of entities, leaving the write to the unit of work.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EntityEmbedder
{
    /**
     * @param positive-int $chunkSize the most texts sent to the model in one call
     */
    public function __construct(
        private readonly VectorizerInterface $vectorizer,
        private readonly int $chunkSize = 100,
    ) {
    }

    /**
     * @param iterable<object> $entities
     */
    public function embed(EntityManagerInterface $entityManager, iterable $entities): void
    {
        $entitiesByClass = [];
        foreach ($entities as $entity) {
            $entitiesByClass[$entityManager->getClassMetadata($entity::class)->getName()][] = $entity;
        }

        foreach ($entitiesByClass as $class => $classEntities) {
            $metadata = $entityManager->getClassMetadata($class);
            $mapping = EmbeddingMapping::fromClassMetadata($metadata);

            if (null === $mapping) {
                throw new InvalidArgumentException(\sprintf('Entity "%s" has no field marked with #[Embedding].', $class));
            }

            $contents = [];
            foreach ($classEntities as $i => $entity) {
                $content = trim($mapping->getContent($entity));

                if ('' === $content) {
                    $mapping->setVector($entityManager, $entity, null);

                    continue;
                }

                $contents[$i] = $content;
            }

            if ([] === $contents) {
                continue;
            }

            foreach (array_chunk($contents, $this->chunkSize, true) as $chunk) {
                /** @var list<VectorInterface> $vectors */
                $vectors = $this->vectorizer->vectorize(array_values($chunk));

                foreach (array_keys($chunk) as $position => $i) {
                    $mapping->setVector($entityManager, $classEntities[$i], $vectors[$position]);
                }
            }
        }
    }
}
