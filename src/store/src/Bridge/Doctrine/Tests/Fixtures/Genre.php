<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

/**
 * Embedded from the inverse side of a many-to-many association, with the text the mapping builds and explicit change tracking.
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Genre implements \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    /** @phpstan-ignore property.onlyRead (Doctrine assigns the generated id by reflection) */
    private int $id;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['name', 'books'])]
    /** @phpstan-ignore property.unusedType (Doctrine assigns the embedding by reflection) */
    private ?VectorInterface $embedding = null;

    /**
     * @var Collection<int, Novel>
     */
    #[ORM\ManyToMany(targetEntity: Novel::class, mappedBy: 'genres')]
    private Collection $books;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->books = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Only called by Novel, which owns the association.
     */
    public function addBook(Novel $book): void
    {
        if (!$this->books->contains($book)) {
            $this->books->add($book);
        }
    }

    public function removeBook(Novel $book): void
    {
        $this->books->removeElement($book);
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }
}
