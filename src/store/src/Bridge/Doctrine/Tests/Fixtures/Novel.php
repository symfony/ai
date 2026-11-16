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
 * Embedded from the owning sides of a many-to-one and a many-to-many association.
 */
#[ORM\Entity]
class Novel implements IndexableEntityInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    /** @phpstan-ignore property.onlyRead (Doctrine assigns the generated id by reflection) */
    private int $id;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title', 'shelf', 'genres'])]
    /** @phpstan-ignore property.unusedType (Doctrine assigns the embedding by reflection) */
    private ?VectorInterface $embedding = null;

    #[ORM\ManyToOne(targetEntity: Shelf::class, inversedBy: 'books')]
    private ?Shelf $shelf = null;

    /**
     * @var Collection<int, Genre>
     */
    #[ORM\ManyToMany(targetEntity: Genre::class, inversedBy: 'books')]
    private Collection $genres;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $title,
    ) {
        $this->genres = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setShelf(?Shelf $shelf): void
    {
        $this->shelf?->removeBook($this);
        $this->shelf = $shelf;
        $shelf?->addBook($this);
    }

    public function addGenre(Genre $genre): void
    {
        if (!$this->genres->contains($genre)) {
            $this->genres->add($genre);
            $genre->addBook($this);
        }
    }

    public function removeGenre(Genre $genre): void
    {
        if ($this->genres->removeElement($genre)) {
            $genre->removeBook($this);
        }
    }

    public function clearGenres(): void
    {
        foreach ($this->genres as $genre) {
            $genre->removeBook($this);
        }

        $this->genres->clear();
    }

    /**
     * @param list<Genre> $genres
     */
    public function replaceGenres(array $genres): void
    {
        foreach ($this->genres as $genre) {
            $genre->removeBook($this);
        }

        $this->genres = new ArrayCollection();
        foreach ($genres as $genre) {
            $this->addGenre($genre);
        }
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }

    public function getIndexableContent(): string
    {
        return implode(' ', array_filter([$this->title, $this->shelf?->getName(), ...array_map(static fn (Genre $genre): string => (string) $genre, $this->genres->toArray())]));
    }
}
