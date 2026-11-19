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

use App\Movies\Data\MovieSuggestion;

/**
 * Resolves the movies the agent suggested to the movies of the collection, ready to render as cards.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Suggestions
{
    public function __construct(
        private readonly MovieRepository $movies,
    ) {
    }

    /**
     * Skips suggestions still being streamed, their id is only complete once the reason follows.
     *
     * @param iterable<MovieSuggestion> $suggestions
     *
     * @return list<array{movie: Movie, reason: string}>
     */
    public function resolve(iterable $suggestions): array
    {
        $resolved = [];
        foreach ($suggestions as $suggestion) {
            if (!isset($suggestion->id, $suggestion->reason) || !ctype_digit($suggestion->id)) {
                continue;
            }

            $movie = $this->movies->find((int) $suggestion->id);
            if (null !== $movie) {
                $resolved[] = ['movie' => $movie, 'reason' => $suggestion->reason];
            }
        }

        return $resolved;
    }
}
