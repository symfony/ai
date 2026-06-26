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
 * Result of a hosted MCP tool invocation performed server-side by the model
 * (e.g. the OpenAI Responses `mcp_call` output item).
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class McpCallResult extends BaseResult
{
    public function __construct(
        private readonly string $serverLabel,
        private readonly string $name,
        private readonly ?string $arguments = null,
        private readonly ?string $output = null,
        private readonly ?string $error = null,
        private readonly ?string $id = null,
        private readonly ?string $status = null,
    ) {
    }

    public function getContent(): ?string
    {
        return $this->output;
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

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
