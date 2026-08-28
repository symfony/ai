<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval\Checkpoint;

use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\Uuid;

/**
 * Immutable snapshot of agent execution state at the point of suspension for approval.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class ExecutionCheckpoint
{
    private readonly string $id;
    private readonly \DateTimeImmutable $createdAt;

    /**
     * @param ToolCall[]           $pendingToolCalls
     * @param ToolResult[]         $completedToolResults
     * @param array<string, mixed> $options
     */
    public function __construct(
        ?string $id = null,
        private readonly string $agentName = 'agent',
        private readonly string $model = '',
        private readonly MessageBag $messages = new MessageBag(),
        private readonly array $options = [],
        private readonly array $pendingToolCalls = [],
        private readonly array $completedToolResults = [],
        private readonly int $iterations = 0,
        private readonly ?SourceCollection $sources = null,
        ?\DateTimeImmutable $createdAt = null,
        private readonly ?\DateTimeImmutable $expiresAt = null,
    ) {
        $this->id = $id ?? (string) Uuid::v7();
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAgentName(): string
    {
        return $this->agentName;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMessages(): MessageBag
    {
        return $this->messages;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return ToolCall[]
     */
    public function getPendingToolCalls(): array
    {
        return $this->pendingToolCalls;
    }

    /**
     * @return ToolResult[]
     */
    public function getCompletedToolResults(): array
    {
        return $this->completedToolResults;
    }

    public function getIterations(): int
    {
        return $this->iterations;
    }

    public function getSources(): ?SourceCollection
    {
        return $this->sources;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        $now ??= new \DateTimeImmutable();

        return $now > $this->expiresAt;
    }
}
