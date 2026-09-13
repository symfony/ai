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
 * Marks a public method as a Mate CLI tool.
 *
 * The method's parameters (plus their `@param` PHPDoc) are reflected into a JSON
 * input schema, and the method is invoked by the `tools:call` command.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class MateTool
{
    public function __construct(
        public string $name,
        public ?string $title = null,
        public ?string $description = null,
    ) {
    }
}
