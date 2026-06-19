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

use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Attribute\AsMcpAppTool;

/**
 * Interactive movie browser exposed as an MCP App.
 *
 * The markup is rendered by Twig — not built in JavaScript. Each tool returns a context array and the
 * bundle renders the template named on the attribute into a server-rendered HTML fragment (the
 * "HTML-over-the-wire" pattern) that the static iframe shell ({@see ../../templates/mcp/movies.html.twig})
 * drops into place:
 *
 *  - `movie_search` (the app's primary tool) returns the result grid ({@see ../../templates/mcp/_movies_grid.html.twig});
 *  - `movie_details` (an app-only follow-up tool) returns a movie's detail view ({@see ../../templates/mcp/_movie_detail.html.twig}).
 *
 * The iframe only wires events to `callTool(...)` and swaps the returned HTML in.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsMcpApp(
    uri: 'ui://movies',
    name: 'movie_search',
    title: 'Movies',
    description: 'Search the movie collection by title, director or cast and browse the results as an interactive grid.',
    template: 'mcp/movies.html.twig',
    toolTemplate: 'mcp/_movies_grid.html.twig',
    prefersBorder: true,
)]
final class MovieApp
{
    public function __construct(
        private readonly MovieSearch $movieSearch,
        private readonly MovieRepository $movies,
    ) {
    }

    /**
     * Search movies and return the matching grid as a context for the result template.
     *
     * @param string $query search term matched against title, director and cast; empty returns all movies
     *
     * @return array{movies: list<Movie>, query: string}
     */
    public function render(string $query = ''): array
    {
        return [
            'movies' => ($this->movieSearch)($query),
            'query' => $query,
        ];
    }

    /**
     * Return a single movie's detail view as a context for the detail template.
     *
     * @param string $slug the movie slug identifier
     *
     * @return array{movie: Movie|null}
     */
    #[AsMcpAppTool(name: 'movie_details', description: 'Show the details of a single movie by its slug.', template: 'mcp/_movie_detail.html.twig', appOnly: true)]
    public function showMovie(string $slug): array
    {
        return [
            'movie' => $this->movies->findOne($slug),
        ];
    }
}
