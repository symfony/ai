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
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

/**
 * Embedded from the inverse side of a one-to-many association, with explicit change tracking.
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Shelf implements IndexableEntityInterface
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
    #[ORM\OneToMany(targetEntity: Novel::class, mappedBy: 'shelf')]
    private Collection $books;

    #[ORM\ManyToOne(targetEntity: Library::class, inversedBy: 'shelves')]
    /** @phpstan-ignore property.onlyWritten (Doctrine reads the association by reflection) */
    private ?Library $library = null;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->books = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setLibrary(Library $library): void
    {
        $this->library = $library;
        $library->getShelves()->add($this);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Only called by Novel::setShelf(), which owns the association.
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

    public function getIndexableContent(): string
    {
        return implode(' ', [$this->name, ...array_map(static fn (Novel $book): string => $book->getTitle(), $this->books->toArray())]);
    }
}
