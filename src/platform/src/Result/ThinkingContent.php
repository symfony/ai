<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * Represents a thinking/reasoning block from a model's response.
 *
 * Yielded by stream generators alongside text strings and ToolCallResult
 * objects. Consumers can check for this type to handle thinking content
 * separately from regular text output.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ThinkingContent
{
    public function __construct(
        public readonly string $thinking,
        public readonly ?string $signature = null,
    ) {
    }
}
