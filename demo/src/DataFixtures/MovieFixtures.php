<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\DataFixtures;

use App\Movies\Movie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * Loads the movies from the Markdown files, the embedding listener embeds them on flush.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MovieFixtures extends Fixture
{
    public function __construct(
        #[Autowire('%movies_dir%')]
        private readonly string $directory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $files = (new Finder())->files()->in($this->directory)->name('*.md')->sortByName();

        foreach ($files as $file) {
            $manager->persist(self::fromFile($file->getRealPath()));
        }

        $manager->flush();
    }

    public static function fromFile(string $path): Movie
    {
        $slug = basename($path, '.md');
        $content = file_get_contents($path);
        $title = $slug;
        $year = null;
        if (preg_match('/^#\s+(.+?)(?:\s*\((\d{4})\))?\s*$/m', (string) $content, $matches)) {
            $title = trim($matches[1]);
            $year = isset($matches[2]) ? (int) $matches[2] : null;
        }

        $director = null;
        if (preg_match('/^\*\*Director:\*\*\s*(.+?)\s*$/m', (string) $content, $matches)) {
            $director = trim($matches[1]);
        }

        $imdb = null;
        if (preg_match('/^\*\*IMDB\*\*:\s*(\S+)/m', (string) $content, $matches)) {
            $imdb = trim($matches[1]);
        }

        $cast = [];
        if (preg_match('/^##\s+Cast\s*$(.*?)(?=^##\s+|\z)/ms', (string) $content, $matches)) {
            preg_match_all('/^-\s*\*\*(.+?)\*\*\s+as\s+(.+?)\s*$/m', $matches[1], $castMatches, \PREG_SET_ORDER);
            foreach ($castMatches as $castMatch) {
                $cast[] = [
                    'name' => trim($castMatch[1]),
                    'role' => trim($castMatch[2]),
                ];
            }
        }

        $plot = [];
        if (preg_match('/^##\s+Plot\s*$(.*?)(?=^##\s+|\z)/ms', (string) $content, $matches)) {
            foreach (preg_split('/\n{2,}/', trim($matches[1])) ?: [] as $paragraph) {
                $paragraph = trim($paragraph);
                if ('' !== $paragraph) {
                    $plot[] = $paragraph;
                }
            }
        }

        return new Movie($slug, $title, $year, $director, $imdb, $cast, $plot);
    }
}
