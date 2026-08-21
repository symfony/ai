<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Attribute;

/**
 * Marks a public method as a Mate static resource, readable by URI through the
 * `resources:read` command.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class AsResource
{
    public function __construct(
        public string $uri,
        public ?string $name = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $mimeType = null,
    ) {
    }
}
