<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures;

final readonly class Movies
{
    /**
     * @return array<array{title: string, description: string, director: string}>
     */
    public static function all(): array
    {
        return [
            ['title' => 'Inception', 'description' => 'A skilled thief is given a chance at redemption if he can successfully perform inception, the act of planting an idea in someone\'s subconscious.', 'director' => 'Christopher Nolan'],
            ['title' => 'The Matrix', 'description' => 'A hacker discovers the world he lives in is a simulated reality and joins a rebellion to overthrow its controllers.', 'director' => 'The Wachowskis'],
            ['title' => 'The Godfather', 'description' => 'The aging patriarch of an organized crime dynasty transfers control of his empire to his reluctant son.', 'director' => 'Francis Ford Coppola'],
            ['title' => 'Notting Hill', 'description' => 'A British bookseller meets and falls in love with a famous American actress, navigating the challenges of fame and romance.', 'director' => 'Roger Michell'],
            ['title' => 'WALL-E', 'description' => 'A small waste-collecting robot inadvertently embarks on a space journey that will decide the fate of mankind.', 'director' => 'Andrew Stanton'],
            ['title' => 'Spirited Away', 'description' => 'A young girl enters a mysterious world of spirits and must find a way to rescue her parents and return home.', 'director' => 'Hayao Miyazaki'],
            ['title' => 'Jurassic Park', 'description' => 'During a preview tour, a theme park suffers a major power breakdown that allows its cloned dinosaur exhibits to run amok.', 'director' => 'Steven Spielberg'],
            ['title' => 'Interstellar', 'description' => 'A team of explorers travel through a wormhole in space in an attempt to ensure humanity\'s survival.', 'director' => 'Christopher Nolan'],
            ['title' => 'The Shawshank Redemption', 'description' => 'Two imprisoned men bond over a number of years, finding solace and eventual redemption through acts of common decency.', 'director' => 'Frank Darabont'],
            ['title' => 'Gladiator', 'description' => 'A former Roman General sets out to exact vengeance against the corrupt emperor who murdered his family and sent him into slavery.', 'director' => 'Ridley Scott'],
            ['title' => 'The Prestige', 'description' => 'Two magicians engage in a competitive battle for supremacy in fin-de-siècle London.', 'director' => 'Christopher Nolan'],
            ['title' => 'Cloud Atlas', 'description' => 'An epic story of how the actions of individuals influence others in different time and place.', 'director' => 'The Wachowskis'],
            ['title' => 'Apocalypse Now', 'description' => 'A US Army officer is sent on a dangerous mission to terminate a renegade Special Forces Colonel in Cambodia.', 'director' => 'Francis Ford Coppola'],
            ['title' => 'The Green Mile', 'description' => 'Death row guards discover a mysterious inmate with the ability to heal people.', 'director' => 'Frank Darabont'],
            ['title' => 'Project Hail Mary', 'description' => 'A teacher and molecular biologist awakens aboard a spacecraft with no memory and must find a way to save Earth from extinction-level alien microorganisms threatening the sun.', 'director' => 'Phil Lord and Chris Miller'],
            ['title' => 'The Dark Knight', 'description' => 'When the menace Joker wreaks havoc and chaos on the people of Gotham, Batman must accept one of the hardest trials of all.', 'director' => 'Christopher Nolan'],
            ['title' => 'Memento', 'description' => 'A man with short-term memory loss attempts to find his wife\'s murderer.', 'director' => 'Christopher Nolan'],
            ['title' => 'Pulp Fiction', 'description' => 'The lives of two mob hitmen, a boxer, a gangster and his wife intertwine in four tales of violence and redemption.', 'director' => 'Quentin Tarantino'],
            ['title' => 'Reservoir Dogs', 'description' => 'A group of criminals gather after a diamond heist gone wrong.', 'director' => 'Quentin Tarantino'],
            ['title' => 'Kill Bill: Vol. 1', 'description' => 'A former assassin seeks revenge against her former boss and colleagues.', 'director' => 'Quentin Tarantino'],
        ];
    }
}
