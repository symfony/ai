<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Completion;

/**
 * Present if the server supports argument autocompletion suggestions.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-completions
 */
final class CompletionCapability
{
    public function __construct(
    ) {
    }
}
