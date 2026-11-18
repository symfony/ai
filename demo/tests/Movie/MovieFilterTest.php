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
use App\Movies\MovieFilter;
use App\Movies\MovieRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MovieFilter::class)]
#[CoversClass(Movie::class)]
final class MovieFilterTest extends TestCase
{
    public function testByDirectorSerializesTheMoviesWithoutEmbedding()
    {
        $repository = $this->createMock(MovieRepository::class);
        $repository->expects($this->once())->method('__call')->with('findByDirector', ['Quentin Tarantino'])->willReturn([$this->movie()]);

        $result = (new MovieFilter($repository))->byDirector('Quentin Tarantino');

        $this->assertSame([[
            'id' => 9,
            'title' => 'Kill Bill: Vol. 1',
            'year' => 2003,
            'director' => 'Quentin Tarantino',
            'cast' => [['name' => 'Uma Thurman', 'role' => 'The Bride']],
        ]], json_decode(json_encode($result, \JSON_THROW_ON_ERROR), true));
    }

    public function testByActorKeepsTheMoviesWithTheCastMember()
    {
        $other = new Movie('the-matrix', 'The Matrix', 1999, 'The Wachowskis', null, [['name' => 'Keanu Reeves', 'role' => 'Neo']], []);
        $repository = $this->createStub(MovieRepository::class);
        $repository->method('findAll')->willReturn([$other, $this->movie()]);

        $result = (new MovieFilter($repository))->byActor('Uma Thurman');

        $this->assertSame(['Kill Bill: Vol. 1'], array_map(static fn (Movie $movie): string => $movie->title, $result));
    }

    public function testByActorIgnoresRoles()
    {
        $repository = $this->createStub(MovieRepository::class);
        $repository->method('findAll')->willReturn([$this->movie()]);

        $this->assertSame([], (new MovieFilter($repository))->byActor('The Bride'));
    }

    private function movie(): Movie
    {
        $movie = new Movie('kill-bill-vol-1', 'Kill Bill: Vol. 1', 2003, 'Quentin Tarantino', null, [['name' => 'Uma Thurman', 'role' => 'The Bride']], []);
        (new \ReflectionProperty(Movie::class, 'id'))->setValue($movie, 9);

        return $movie;
    }
}
