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
use App\Movies\MovieRepository;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\RetrieverInterface;

/**
 * Serves the Markdown fixtures through a stubbed repository and a retriever matching the text of a movie.
 */
trait MovieCollectionTrait
{
    private function repository(): MovieRepository
    {
        $movies = [];
        foreach (glob(__DIR__.'/../../../fixtures/movies/*.md') ?: [] as $path) {
            $movies[] = MovieFixtures::fromFile($path);
        }

        $repository = $this->createStub(MovieRepository::class);
        $repository->method('findAll')->willReturn($movies);
        // findOneBySlug() is a magic finder of the repository
        $repository->method('__call')->willReturnCallback(static function (string $method, array $arguments) use ($movies): ?Movie {
            foreach ($movies as $movie) {
                if ($arguments[0] === $movie->slug) {
                    return $movie;
                }
            }

            return null;
        });

        return $repository;
    }

    private function retriever(MovieRepository $repository): RetrieverInterface
    {
        return new class($repository) implements RetrieverInterface {
            public function __construct(
                private readonly MovieRepository $repository,
            ) {
            }

            public function retrieve(string $query, array $options = []): iterable
            {
                $limit = $options['limit'] ?? 5;
                foreach ($this->repository->findAll() as $movie) {
                    if ($limit > 0 && str_contains(mb_strtolower($movie->getIndexableContent()), mb_strtolower($query))) {
                        --$limit;

                        yield new EntityVectorDocument($movie, (string) $movie->getId(), new Vector([1.0]));
                    }
                }
            }
        };
    }
}
