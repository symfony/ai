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

/**
 * Inherits the vector from the table of its parent class.
 */
#[ORM\Entity]
#[ORM\Table(name: 'book')]
class Book extends Publication
{
    public function __construct(
        string $title,
        #[ORM\Column(type: 'string')]
        private string $isbn,
    ) {
        parent::__construct($title);
    }

    public function getIsbn(): string
    {
        return $this->isbn;
    }
}
