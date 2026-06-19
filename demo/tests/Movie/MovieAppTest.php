<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Movie;

use App\Movies\Movie;
use App\Movies\MovieApp;
use App\Movies\MovieRepository;
use App\Movies\MovieSearch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MovieApp::class)]
final class MovieAppTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/movies';

    public function testRenderReturnsAllMoviesForEmptyQuery()
    {
        $result = $this->app()->render('');

        $this->assertSame('', $result['query']);
        $slugs = $this->slugs($result['movies']);
        $this->assertContains('oppenheimer', $slugs);
        $this->assertContains('forrest-gump', $slugs);
    }

    public function testRenderNarrowsByDirector()
    {
        $slugs = $this->slugs($this->app()->render('Tarantino')['movies']);

        $this->assertContains('django-unchained', $slugs);
        $this->assertNotContains('oppenheimer', $slugs);
    }

    public function testRenderNarrowsByCast()
    {
        // Cillian Murphy plays in Oppenheimer.
        $slugs = $this->slugs($this->app()->render('Cillian')['movies']);

        $this->assertContains('oppenheimer', $slugs);
        $this->assertNotContains('forrest-gump', $slugs);
    }

    public function testShowMovieReturnsTheMovie()
    {
        $movie = $this->app()->showMovie('oppenheimer')['movie'];

        $this->assertInstanceOf(Movie::class, $movie);
        $this->assertSame('Oppenheimer', $movie->title);
    }

    public function testShowMovieHandlesUnknownSlug()
    {
        $this->assertNull($this->app()->showMovie('does-not-exist')['movie']);
    }

    /**
     * @param list<Movie> $movies
     *
     * @return list<string>
     */
    private function slugs(array $movies): array
    {
        return array_map(static fn (Movie $movie): string => $movie->slug, $movies);
    }

    private function app(): MovieApp
    {
        $repository = new MovieRepository(self::FIXTURES_DIR);

        return new MovieApp(new MovieSearch($repository), $repository);
    }
}
