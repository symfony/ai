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
final readonly class Article
{
    /**
     * @param list<string> $components
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $icon,
        public array $components,
    ) {
    }
}
