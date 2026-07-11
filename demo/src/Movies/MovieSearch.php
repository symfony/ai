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
 * Plain in-memory search over the movie collection, exposed to the agent as a tool.
 *
 * No vector store is involved: the agent calls this tool with a free-text query and gets the matching
 * movies back - including their "slug", which it then reuses to reference movies in its structured answer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool(
    name: 'movie_search',
    description: 'Search the movie collection by title, director or cast member. Returns the matching movies, each with a "slug" you must reuse to reference a movie in your answer. Pass an empty query to list the whole collection.',
)]
final class MovieSearch
{
    public function __construct(
        private readonly MovieRepository $movies,
    ) {
    }

    /**
     * @param string $query Search term matched against title, director and cast; pass an empty string to list all movies
     *
     * @return list<Movie> the matching movies (serialized to the model via {@see Movie::jsonSerialize()})
     */
    public function __invoke(string $query = ''): array
    {
        $needle = mb_strtolower(trim($query));

        $results = [];
        foreach ($this->movies->findAll() as $movie) {
            if ('' !== $needle && !$this->matches($movie, $needle)) {
                continue;
            }

            $results[] = $movie;
        }

        return $results;
    }

    private function matches(Movie $movie, string $needle): bool
    {
        if (str_contains(mb_strtolower($movie->title), $needle)) {
            return true;
        }

        if (str_contains(mb_strtolower($movie->director ?? ''), $needle)) {
            return true;
        }

        foreach ($movie->cast as $member) {
            if (str_contains(mb_strtolower($member['name']), $needle)) {
                return true;
            }

            if (str_contains(mb_strtolower($member['role']), $needle)) {
                return true;
            }
        }

        return false;
    }
}
