<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\DataFixtures;

use App\DataFixtures\MovieFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MovieFixtures::class)]
final class MovieFixturesTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/movies';

    public function testParsesCloudAtlasFixture()
    {
        $movie = MovieFixtures::fromFile(self::FIXTURES_DIR.'/cloud-atlas.md');

        $this->assertSame('cloud-atlas', $movie->slug);
        $this->assertSame('Cloud Atlas', $movie->title);
        $this->assertSame(2012, $movie->year);
        $this->assertSame('The Wachowskis', $movie->director);
        $this->assertSame('https://www.imdb.com/title/tt0443001/', $movie->imdb);
        $this->assertSame(['name' => 'Tom Hanks', 'role' => 'Various Roles'], $movie->cast[0]);
        $this->assertCount(4, $movie->cast);
        $this->assertNotSame([], $movie->plot);
    }

    public function testParsesMatrixFixture()
    {
        $movie = MovieFixtures::fromFile(self::FIXTURES_DIR.'/the-matrix.md');

        $this->assertSame('the-matrix', $movie->slug);
        $this->assertSame('The Matrix', $movie->title);
        $this->assertSame(1999, $movie->year);
        $this->assertSame('The Wachowskis', $movie->director);
        $this->assertCount(4, $movie->cast);
        $this->assertSame('Keanu Reeves', $movie->cast[0]['name']);
        $this->assertSame('Neo', $movie->cast[0]['role']);
    }

    public function testLeavesOptionalFieldsNullForMinimalMarkdown()
    {
        $path = sys_get_temp_dir().'/movie-minimal-'.uniqid().'.md';
        file_put_contents($path, "# Nameless\n\nJust a title, nothing else.\n");

        try {
            $movie = MovieFixtures::fromFile($path);

            $this->assertSame('Nameless', $movie->title);
            $this->assertNull($movie->year);
            $this->assertNull($movie->director);
            $this->assertNull($movie->imdb);
            $this->assertSame([], $movie->cast);
            $this->assertSame([], $movie->plot);
        } finally {
            unlink($path);
        }
    }
}
