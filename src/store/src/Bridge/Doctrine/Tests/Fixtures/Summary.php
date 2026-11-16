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

/**
 * Identified by the article it summarizes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'summary')]
class Summary
{
    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['text'])]
    /** @phpstan-ignore property.unusedType (Doctrine assigns the embedding by reflection) */
    private ?VectorInterface $embedding = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne(targetEntity: Article::class)]
        #[ORM\JoinColumn(name: 'article_id')]
        private Article $article,
        #[ORM\Column(type: 'string')]
        private string $text,
    ) {
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }
}
