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
class Translation
{
    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['key'])]
    /** @phpstan-ignore property.unusedType (Doctrine assigns the embedding by reflection) */
    private ?VectorInterface $embedding = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        private string $key,
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        private string $locale,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }
}
