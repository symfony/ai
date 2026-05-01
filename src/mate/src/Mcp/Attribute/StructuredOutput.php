<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Mcp\Attribute;

/**
 * Marks an MCP tool method as producing structured output.
 *
 * When present, Mate will:
 *  - Generate a JSON Schema from the method's `@phpstan-return` / `@return` docblock
 *    and advertise it on the tool's `outputSchema` field.
 *  - Populate the MCP `structuredContent` channel from the method's return value.
 *
 * Without this attribute, the method's result is encoded via {@see \Symfony\AI\Mate\Encoding\ResponseEncoder}
 * (TOON when available, JSON otherwise) into the text content block only — no
 * `structuredContent`, no `outputSchema`. This preserves token efficiency for
 * tools that return large or repeated payloads.
 *
 * Use this on small, fixed-shape tools where machine-readable structure helps
 * the client/LLM more than TOON savings would (discovery, single-resource lookups).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class StructuredOutput
{
}
