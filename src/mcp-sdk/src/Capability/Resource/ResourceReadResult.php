<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Capability\Resource;

final readonly class ResourceReadResult
{
    public function __construct(
        public string $result,
        public string $uri,

        /**
         * @var "text"|"blob"
         */
        public string $type = 'text',
        public string $mimeType = 'text/plain',
    ) {
    }
}
