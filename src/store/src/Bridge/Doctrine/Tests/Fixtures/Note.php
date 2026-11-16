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

use Doctrine\ORM\Mapping as ORM;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Note implements IndexableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title'])]
    /** @phpstan-ignore property.unusedType (Doctrine assigns the embedding by reflection) */
    private ?VectorInterface $embedding = null;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $title,
    ) {
        $this->id = new Ulid();
    }

    public function getId(): Ulid
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

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }

    public function getIndexableContent(): string
    {
        return 'Note: '.$this->title;
    }
}
