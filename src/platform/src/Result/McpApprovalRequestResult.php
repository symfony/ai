<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * Approval request emitted by the model before invoking a hosted MCP tool
 * (e.g. the OpenAI Responses `mcp_approval_request` output item).
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class McpApprovalRequestResult extends BaseResult
{
    public function __construct(
        private readonly string $serverLabel,
        private readonly string $name,
        private readonly ?string $arguments = null,
        private readonly ?string $id = null,
    ) {
    }

    public function getContent(): ?string
    {
        return $this->arguments;
    }

    public function getServerLabel(): string
    {
        return $this->serverLabel;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): ?string
    {
        return $this->arguments;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
