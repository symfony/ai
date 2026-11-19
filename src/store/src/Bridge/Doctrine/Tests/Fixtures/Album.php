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

/**
 * Owns a many-to-many association by a composite identifier.
 */
#[ORM\Entity]
class Album implements \Stringable
{
    /**
     * @var Collection<int, Track>
     */
    #[ORM\ManyToMany(targetEntity: Track::class, inversedBy: 'albums')]
    #[ORM\JoinTable(name: 'album_track')]
    #[ORM\JoinColumn(name: 'album_artist', referencedColumnName: 'artist')]
    #[ORM\JoinColumn(name: 'album_title', referencedColumnName: 'title')]
    private Collection $tracks;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        private string $artist,
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        private string $title,
    ) {
        $this->tracks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getArtist(): string
    {
        return $this->artist;
    }

    public function addTrack(Track $track): void
    {
        $this->tracks->add($track);
        $track->addAlbum($this);
    }

    public function removeTrack(Track $track): void
    {
        $this->tracks->removeElement($track);
        $track->removeAlbum($this);
    }
}
