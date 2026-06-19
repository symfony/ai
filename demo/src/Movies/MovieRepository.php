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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MovieRepository
{
    public function __construct(
        #[Autowire('%movies_dir%')]
        private readonly string $directory,
    ) {
    }

    /**
     * @return list<Movie>
     */
    public function findAll(): array
    {
        $finder = (new Finder())
            ->files()
            ->in($this->directory)
            ->name('*.md')
            ->sortByName();

        $movies = [];
        foreach ($finder as $file) {
            $movies[] = Movie::fromFile($file->getRealPath());
        }

        return $movies;
    }

    public function findOne(string $slug): ?Movie
    {
        if ('' === $slug || str_contains($slug, '/') || str_contains($slug, '..')) {
            return null;
        }

        $path = $this->directory.\DIRECTORY_SEPARATOR.$slug.'.md';
        if (!is_file($path)) {
            return null;
        }

        return Movie::fromFile($path);
    }
}
