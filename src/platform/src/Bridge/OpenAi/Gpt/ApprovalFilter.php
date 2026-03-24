<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Gpt;

/**
 * Represents a granular approval filter for MCP tools.
 *
 * Allows skipping approval for specific tool names while requiring it for others.
 *
 * @see https://developers.openai.com/api/docs/guides/tools-connectors-mcp
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApprovalFilter
{
    /**
     * @param list<string> $never Tool names that skip approval
     */
    public function __construct(
        private readonly array $never,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getNever(): array
    {
        return $this->never;
    }
}
