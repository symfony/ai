<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Tool;

final readonly class ToolCallResult
{
    public function __construct(
        public mixed $result,
        /**
         * @var "text"|"image"|"audio"|"resource"|non-empty-string
         */
        public string $type = 'text',
        public string $mimeType = 'text/plain',
        public bool $isError = false,
        public ?string $uri = null,
    ) {
    }
}
