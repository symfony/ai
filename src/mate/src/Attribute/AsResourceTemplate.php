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
 * Marks a public method as a Mate resource template.
 *
 * The `uriTemplate` uses RFC 6570-style `{variable}` placeholders. When a URI passed
 * to the `resources:read` command matches the template, the placeholder values are
 * extracted and passed to the method as arguments (matched by parameter name).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class AsResourceTemplate
{
    public function __construct(
        public string $uriTemplate,
        public ?string $name = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $mimeType = null,
    ) {
    }
}
