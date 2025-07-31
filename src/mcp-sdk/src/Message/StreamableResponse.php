<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Message;

final readonly class StreamableResponse
{
    /**
     * @param iterable<Response> $responses
     */
    public function __construct(
        public \iterable $responses,
    ) {
    }
}
