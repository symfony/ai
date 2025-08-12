<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Field;

/**
 * The _meta property/parameter is reserved by MCP to allow clients and servers to attach additional metadata to their interactions. Certain key names are reserved by MCP for protocol-level metadata, as specified below; implementations MUST NOT make assumptions about values at these keys. Additionally, definitions in the schema may reserve particular names for purpose-specific metadata, as declared in those definitions. Key name format: valid _meta key names have two segments: an optional prefix, and a name. Prefix:
 *
 * If specified, MUST be a series of labels separated by dots (.), followed by a slash (/).
 * Labels MUST start with a letter and end with a letter or digit; interior characters can be letters, digits, or hyphens (-).
 * Any prefix beginning with zero or more valid labels, followed by modelcontextprotocol or mcp, followed by any valid label, is reserved for MCP use.
 * For example: modelcontextprotocol.io/, mcp.dev/, api.modelcontextprotocol.org/, and tools.mcp.com/ are all reserved.
 *
 * Name:
 *
 * Unless empty, MUST begin and end with an alphanumeric character ([a-z0-9A-Z]).
 * MAY contain hyphens (-), underscores (_), dots (.), and alphanumerics in between.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/index#general-fields
 */
final readonly class MetaField
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public array $meta,
    ) {
    }
}
