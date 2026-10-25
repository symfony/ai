<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Movies;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ORM\Index(name: 'movie_embedding_idx', columns: ['embedding'])]
class Movie implements IndexableEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title', 'year', 'director', 'cast', 'plot'])]
    private ?VectorInterface $embedding = null;

    /**
     * @param list<array{name: string, role: string}> $cast
     * @param list<string>                            $plot
     */
    public function __construct(
        #[ORM\Column(length: 100, unique: true)]
        public readonly string $slug,
        #[ORM\Column]
        public readonly string $title,
        #[ORM\Column(nullable: true)]
        public readonly ?int $year,
        #[ORM\Column(nullable: true)]
        public readonly ?string $director,
        #[ORM\Column(nullable: true)]
        public readonly ?string $imdb,
        #[ORM\Column(name: 'cast_members', type: Types::JSON)]
        public readonly array $cast,
        #[ORM\Column(type: Types::JSON)]
        public readonly array $plot,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function hue(): int
    {
        return crc32($this->slug) % 360;
    }

    public function getIndexableContent(): string
    {
        $lines = [null === $this->year ? $this->title : \sprintf('%s (%d)', $this->title, $this->year)];

        if (null !== $this->director) {
            $lines[] = 'Directed by '.$this->director;
        }

        if ([] !== $this->cast) {
            $lines[] = 'Starring '.implode(', ', array_map(static fn (array $member): string => \sprintf('%s as %s', $member['name'], $member['role']), $this->cast));
        }

        return implode(\PHP_EOL, [...$lines, ...$this->plot]);
    }
}
