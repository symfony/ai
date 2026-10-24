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

use App\DataFixtures\MovieFixtures;
use App\Movies\Movie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Movie::class)]
final class MovieTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/movies';

    public function testIndexableContentDescribesTheMovie()
    {
        $movie = MovieFixtures::fromFile(self::FIXTURES_DIR.'/the-matrix.md');

        $lines = explode(\PHP_EOL, $movie->getIndexableContent());

        $this->assertSame('The Matrix (1999)', $lines[0]);
        $this->assertSame('Directed by The Wachowskis', $lines[1]);
        $this->assertStringStartsWith('Starring Keanu Reeves as Neo, ', $lines[2]);
        $this->assertSame($movie->plot, \array_slice($lines, 3));
    }

    public function testIndexableContentLeavesOutMissingDetails()
    {
        $movie = new Movie('untitled', 'Untitled', null, null, null, [], ['A plot.']);

        $this->assertSame('Untitled'.\PHP_EOL.'A plot.', $movie->getIndexableContent());
    }
}
