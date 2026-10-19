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
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Note
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'vector', nullable: true)]
    /** @phpstan-ignore property.unusedType, property.onlyWritten (Store writes the column, Doctrine only maps it) */
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
}
