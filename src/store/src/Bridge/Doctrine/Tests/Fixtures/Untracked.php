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

#[ORM\Entity]
class Untracked
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    /** @phpstan-ignore property.unused (Doctrine assigns the generated id by reflection) */
    private int $id;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $title,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
