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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Exact lookups by a concrete characteristic of a movie, where a similarity search would also return lookalikes.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MovieFilter
{
    public function __construct(
        private readonly MovieRepository $movies,
    ) {
    }

    /**
     * @param string $director the full name of the director
     *
     * @return array<int, Movie>
     */
    #[AsTool('search_by_director', 'Find all movies of a director by their full name, e.g. "Quentin Tarantino" or "Christopher Nolan".')]
    public function byDirector(string $director): array
    {
        return $this->movies->findByDirector($director);
    }

    /**
     * @param string $actor the full name of the actor or actress
     *
     * @return list<Movie>
     */
    #[AsTool('search_by_actor', 'Find all movies an actor or actress plays in by their full name, e.g. "Keanu Reeves" or "Uma Thurman".')]
    public function byActor(string $actor): array
    {
        return array_values(array_filter(
            $this->movies->findAll(),
            static fn (Movie $movie): bool => \in_array($actor, array_column($movie->cast, 'name'), true),
        ));
    }
}
