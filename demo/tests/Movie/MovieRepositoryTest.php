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
use App\Movies\MovieRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(MovieRepository::class)]
final class MovieRepositoryTest extends TestCase
{
    private string $directory;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/movie-repo-'.uniqid();
        $this->fs = new Filesystem();
        $this->fs->mkdir($this->directory);

        $this->fs->dumpFile($this->directory.'/alpha.md', "# Alpha (2001)\n\n**Director:** Someone\n");
        $this->fs->dumpFile($this->directory.'/beta.md', "# Beta (2002)\n\n**Director:** Another\n");
        $this->fs->dumpFile($this->directory.'/notes.txt', 'should be ignored');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->directory);
    }

    public function testAllReturnsMoviesSortedByFilename()
    {
        $repository = new MovieRepository($this->directory);

        $movies = $repository->findAll();

        $this->assertCount(2, $movies);
        /* @phpstan-ignore-next-line method.alreadyNarrowedTypy */
        $this->assertContainsOnlyInstancesOf(Movie::class, $movies);
        $this->assertSame(['alpha', 'beta'], array_map(static fn (Movie $m) => $m->slug, $movies));
    }

    public function testFindReturnsMovieWhenFilePresent()
    {
        $repository = new MovieRepository($this->directory);

        $movie = $repository->findOne('alpha');

        $this->assertNotNull($movie);
        $this->assertSame('Alpha', $movie->title);
        $this->assertSame(2001, $movie->year);
    }

    public function testFindReturnsNullForUnknownSlug()
    {
        $repository = new MovieRepository($this->directory);

        $this->assertNull($repository->findOne('gamma'));
    }

    public function testFindRejectsEmptyAndTraversalSlugs()
    {
        $repository = new MovieRepository($this->directory);

        $this->assertNull($repository->findOne(''));
        $this->assertNull($repository->findOne('../secret'));
        $this->assertNull($repository->findOne('nested/path'));
    }
}
