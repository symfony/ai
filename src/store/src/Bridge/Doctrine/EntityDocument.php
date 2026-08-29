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

use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * An embeddable document that stands for a Doctrine entity.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EntityDocument implements EmbeddableDocumentInterface
{
    public function __construct(
        private readonly object $entity,
        private readonly int|string $id,
        private readonly string $content,
        private readonly Metadata $metadata = new Metadata(),
    ) {
        if ('' === trim($this->content)) {
            throw new InvalidArgumentException(\sprintf('The indexable content of "%s" shall not be an empty string.', $entity::class));
        }
    }

    /**
     * @param string|null $content the text to embed, defaulting to what the entity itself declares
     */
    public static function fromEntity(object $entity, int|string $id, ?string $content = null, Metadata $metadata = new Metadata()): self
    {
        if (null === $content) {
            if (!$entity instanceof IndexableEntityInterface) {
                throw new InvalidArgumentException(\sprintf('Cannot derive indexable content from "%s", let it implement "%s" or pass the content explicitly.', $entity::class, IndexableEntityInterface::class));
            }

            $content = $entity->getIndexableContent();
        }

        return new self($entity, $id, $content, $metadata);
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }
}
