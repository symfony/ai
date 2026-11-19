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
use Symfony\Component\Uid\Ulid;

/**
 * Owns a many-to-many association by a ULID, which SQLite stores as binary.
 */
#[ORM\Entity]
class Playlist implements \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /**
     * @var Collection<int, Track>
     */
    #[ORM\ManyToMany(targetEntity: Track::class, inversedBy: 'playlists')]
    private Collection $tracks;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->id = new Ulid();
        $this->tracks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function addTrack(Track $track): void
    {
        $this->tracks->add($track);
        $track->addPlaylist($this);
    }

    public function clearTracks(): void
    {
        foreach ($this->tracks as $track) {
            $track->removePlaylist($this);
        }

        $this->tracks->clear();
    }
}
