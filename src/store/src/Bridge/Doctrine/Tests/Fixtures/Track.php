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
 * Embedded from the inverse sides of many-to-many associations whose owners have a ULID or a composite identifier.
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Track
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    /** @phpstan-ignore property.onlyRead (Doctrine assigns the generated id by reflection) */
    private int $id;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title', 'playlists', 'albums'])]
    /** @phpstan-ignore property.unusedType (Doctrine assigns the embedding by reflection) */
    private ?VectorInterface $embedding = null;

    /**
     * @var Collection<int, Playlist>
     */
    #[ORM\ManyToMany(targetEntity: Playlist::class, mappedBy: 'tracks')]
    private Collection $playlists;

    /**
     * @var Collection<int, Album>
     */
    #[ORM\ManyToMany(targetEntity: Album::class, mappedBy: 'tracks')]
    private Collection $albums;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $title,
    ) {
        $this->playlists = new ArrayCollection();
        $this->albums = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Only called by Playlist, which owns the association.
     */
    public function addPlaylist(Playlist $playlist): void
    {
        $this->playlists->add($playlist);
    }

    public function removePlaylist(Playlist $playlist): void
    {
        $this->playlists->removeElement($playlist);
    }

    /**
     * Only called by Album, which owns the association.
     */
    public function addAlbum(Album $album): void
    {
        $this->albums->add($album);
    }

    public function removeAlbum(Album $album): void
    {
        $this->albums->removeElement($album);
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }
}
