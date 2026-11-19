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

/**
 * Asks the worker to answer the latest user message of the movie chat.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Answer
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $topic,
    ) {
    }
}
