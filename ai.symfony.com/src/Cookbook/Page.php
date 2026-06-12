<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Cookbook;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Page
{
    public function __construct(
        public Article $article,
        public string $body,
        public ?Article $previous,
        public ?Article $next,
    ) {
    }
}
