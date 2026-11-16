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
#[ORM\Index(name: 'article_embedding_idx', columns: ['embedding'])]
#[ORM\Table(name: 'article')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    /** @phpstan-ignore property.onlyRead (Doctrine assigns the generated id by reflection) */
    private int $id;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title', 'body', 'tags'])]
    private ?VectorInterface $embedding = null;

    /**
     * @var list<string|array<string, string>>
     */
    #[ORM\Column(type: 'json')]
    /** @phpstan-ignore property.onlyWritten (the embedding mapping reads the tags through the class metadata) */
    private array $tags = [];

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $title,
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $body = null,
        #[ORM\Column(type: 'integer')]
        private int $views = 0,
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

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @param list<string|array<string, string>> $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): void
    {
        $this->views = $views;
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }

    public function setEmbedding(?VectorInterface $embedding): void
    {
        $this->embedding = $embedding;
    }
}
