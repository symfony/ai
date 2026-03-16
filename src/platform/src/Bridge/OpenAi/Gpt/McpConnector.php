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
 * Represents a built-in connector tool definition for OpenAI's Responses API.
 *
 * @see https://developers.openai.com/api/docs/guides/tools-connectors-mcp
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpConnector
{
    /**
     * @param string                $connectorId       Identifier for the built-in connector (e.g. "connector_gmail")
     * @param string                $serverLabel       Identifier for the connector, used in response items
     * @param string|null           $serverDescription Optional description helping the model understand the connector's purpose
     * @param string|null           $authorization     OAuth access token for the connector
     * @param string|ApprovalFilter $requireApproval   Approval configuration: "always", "never", or an ApprovalFilter
     * @param list<string>|null     $allowedTools      Restricts which tools the model can access from the connector
     */
    public function __construct(
        private readonly string $connectorId,
        private readonly string $serverLabel,
        private readonly ?string $serverDescription = null,
        private readonly ?string $authorization = null,
        private readonly string|ApprovalFilter $requireApproval = 'never',
        private readonly ?array $allowedTools = null,
    ) {
    }

    public function getConnectorId(): string
    {
        return $this->connectorId;
    }

    public function getServerLabel(): string
    {
        return $this->serverLabel;
    }

    public function getServerDescription(): ?string
    {
        return $this->serverDescription;
    }

    public function getAuthorization(): ?string
    {
        return $this->authorization;
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
