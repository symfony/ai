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

use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\RetrieverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Searches the movies for the result grid of the MCP app, the agent uses the similarity search tool.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MovieSearch
{
    public function __construct(
        private readonly MovieRepository $movies,
        #[Autowire(service: 'ai.retriever.movies')]
        private readonly RetrieverInterface $retriever,
    ) {
    }

    /**
     * @param string $query What to look for, e.g. "space exploration" or "Keanu Reeves"; pass an empty string to list all movies
     * @param int    $limit The maximum number of movies to return
     *
     * @return list<Movie> the matching movies, best match first
     */
    public function __invoke(string $query = '', int $limit = 5): array
    {
        if ('' === trim($query)) {
            return $this->movies->findAll();
        }

        $movies = [];
        foreach ($this->retriever->retrieve($query, ['limit' => max(1, $limit)]) as $document) {
            if ($document instanceof EntityVectorDocument && $document->getEntity() instanceof Movie) {
                $movies[] = $document->getEntity();
            }
        }

        return $movies;
    }
}
