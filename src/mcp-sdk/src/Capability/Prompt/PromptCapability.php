<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Prompt;

/**
 * Present if the server offers any prompt templates.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/schema#servercapabilities-logging
 */
final readonly class PromptCapability
{
    public function __construct(
        /** Whether this server supports notifications for changes to the prompt list. */
        public ?bool $listChanged = null,
    ) {
    }
}
