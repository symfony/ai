<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Movies\Data;

/**
 * One movie the assistant decided to surface as a card.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MovieSuggestion
{
    /**
     * @var string The exact id of a movie returned by the movie_search tool
     */
    public string $id;

    /**
     * @var string One short sentence on why this movie fits the user's request
     */
    public string $reason;
}
