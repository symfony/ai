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
 * Result of a tool call the provider ran itself, like a tool search.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ServerToolCallResult extends BaseResult
{
    /**
     * @param string               $name    Provider independent name of the tool, e.g. "tool_search"
     * @param string|null          $id      Identifier of the call, as assigned by the provider
     * @param string|null          $status  Provider-reported status of the call, e.g. "completed" or an error code
     * @param array<string, mixed> $details Display only information, e.g. the query and the found tools
     * @param string|null          $replay  Opaque, provider-scoped payload needed to send the call back
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $id = null,
        private readonly ?string $status = null,
        private readonly array $details = [],
        private readonly ?string $replay = null,
    ) {
    }

    public function getContent(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getReplay(): ?string
    {
        return $this->replay;
    }
}
