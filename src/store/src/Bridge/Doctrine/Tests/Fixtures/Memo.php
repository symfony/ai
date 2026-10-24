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
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

#[ORM\Entity]
#[ORM\Table(name: 'memo')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Memo
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    /** @phpstan-ignore property.onlyRead (Doctrine assigns the generated id by reflection) */
    private int $id;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title'])]
    /** @phpstan-ignore property.unusedType (Doctrine assigns the embedding by reflection) */
    private ?VectorInterface $embedding = null;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $title,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }
}
