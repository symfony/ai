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
use App\Movies\MovieSearch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MovieSearch::class)]
final class MovieSearchTest extends TestCase
{
    use MovieCollectionTrait;

    public function testEmptyQueryReturnsWholeCollection()
    {
        $slugs = $this->slugsFor('');

        $this->assertNotSame([], $slugs);
        $this->assertContains('the-matrix', $slugs);
    }

    public function testSearchMatchesTitle()
    {
        $slugs = $this->slugsFor('matrix');

        $this->assertContains('the-matrix', $slugs);
    }

    public function testSearchMatchesDirector()
    {
        $slugs = $this->slugsFor('Wachowski');

        $this->assertContains('the-matrix', $slugs);
        $this->assertContains('cloud-atlas', $slugs);
    }

    public function testSearchMatchesCastMember()
    {
        $slugs = $this->slugsFor('Keanu Reeves');

        $this->assertContains('the-matrix', $slugs);
    }

    public function testSearchLimitsTheNumberOfMovies()
    {
        $this->assertCount(2, ($this->search())('the', 2));
    }

    public function testSearchReturnsEmptyArrayForNoMatch()
    {
        $this->assertSame([], ($this->search())('this-does-not-match-anything'));
    }

    /**
     * @return list<string>
     */
    private function slugsFor(string $query): array
    {
        return array_map(static fn (Movie $movie): string => $movie->slug, ($this->search())($query));
    }

    private function search(): MovieSearch
    {
        $repository = $this->repository();

        return new MovieSearch($repository, $this->retriever($repository));
    }
}
