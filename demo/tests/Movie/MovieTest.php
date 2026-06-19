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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Movie::class)]
final class MovieTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/movies';

    public function testFromFileParsesCloudAtlasFixture()
    {
        $movie = Movie::fromFile(self::FIXTURES_DIR.'/cloud-atlas.md');

        $this->assertSame('cloud-atlas', $movie->slug);
        $this->assertSame('Cloud Atlas', $movie->title);
        $this->assertSame(2012, $movie->year);
        $this->assertSame('The Wachowskis', $movie->director);
        $this->assertSame('https://www.imdb.com/title/tt0443001/', $movie->imdb);
        $this->assertSame(['name' => 'Tom Hanks', 'role' => 'Various Roles'], $movie->cast[0]);
        $this->assertCount(4, $movie->cast);
        $this->assertNotSame([], $movie->plot);
    }

    public function testFromFileParsesMatrixFixture()
    {
        $movie = Movie::fromFile(self::FIXTURES_DIR.'/the-matrix.md');

        $this->assertSame('the-matrix', $movie->slug);
        $this->assertSame('The Matrix', $movie->title);
        $this->assertSame(1999, $movie->year);
        $this->assertSame('The Wachowskis', $movie->director);
        $this->assertCount(4, $movie->cast);
        $this->assertSame('Keanu Reeves', $movie->cast[0]['name']);
        $this->assertSame('Neo', $movie->cast[0]['role']);
    }

    public function testFromFileWithMinimalMarkdownLeavesOptionalFieldsNull()
    {
        $path = sys_get_temp_dir().'/movie-minimal-'.uniqid().'.md';
        file_put_contents($path, "# Nameless\n\nJust a title, nothing else.\n");

        try {
            $movie = Movie::fromFile($path);

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

    public function testFromFileExposesRawMarkdown()
    {
        $movie = Movie::fromFile(self::FIXTURES_DIR.'/cloud-atlas.md');

        $this->assertStringContainsString('## Plot', $movie->rawMarkdown);
        $this->assertStringContainsString('**Director:** The Wachowskis', $movie->rawMarkdown);
    }

    public function testJsonSerializeReturnsCompactModelFacingProjection()
    {
        $movie = Movie::fromFile(self::FIXTURES_DIR.'/the-matrix.md');

        $data = $movie->jsonSerialize();

        // exactly the fields the agent needs — no full plot or raw markdown
        $this->assertSame(['slug', 'title', 'year', 'director', 'cast', 'summary'], array_keys($data));
        $this->assertSame('the-matrix', $data['slug']);
        $this->assertSame('The Matrix', $data['title']);
        $this->assertSame(1999, $data['year']);
        $this->assertSame('The Wachowskis', $data['director']);
        $this->assertSame('Keanu Reeves', $data['cast'][0]); // cast reduced to names only
        $this->assertSame($movie->plot[0], $data['summary']); // summary is the first plot paragraph
    }
}
