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
 * Represents a remote MCP server tool definition for OpenAI's Responses API.
 *
 * @see https://developers.openai.com/api/docs/guides/tools-connectors-mcp
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpTool
{
    /**
     * @param string                     $serverLabel       Identifier for the MCP server, used in response items
     * @param string                     $serverUrl         The endpoint URL for the remote MCP server
     * @param string|null                $serverDescription Optional description helping the model understand the server's purpose
     * @param array<string, string>|null $headers           Custom HTTP headers to send with requests to the MCP server
     * @param string|ApprovalFilter      $requireApproval   Approval configuration: "always", "never", or an ApprovalFilter
     * @param list<string>|null          $allowedTools      Restricts which tools the model can access from the server
     */
    public function __construct(
        private readonly string $serverLabel,
        private readonly string $serverUrl,
        private readonly ?string $serverDescription = null,
        private readonly ?array $headers = null,
        private readonly string|ApprovalFilter $requireApproval = 'never',
        private readonly ?array $allowedTools = null,
    ) {
    }

    public function getServerLabel(): string
    {
        return $this->serverLabel;
    }

    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    public function getServerDescription(): ?string
    {
        return $this->serverDescription;
    }

    /**
     * @return array<string, string>|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function getRequireApproval(): string|ApprovalFilter
    {
        return $this->requireApproval;
    }

    /**
     * @return list<string>|null
     */
    public function getAllowedTools(): ?array
    {
        return $this->allowedTools;
    }
}
