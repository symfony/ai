<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval;

/**
 * @author Saiful Islam <saif012@gmail.com>
 */
final class ApprovalDecision
{
    /**
     * @param array<string, mixed>|null $modifiedArguments
     */
    public function __construct(
        private readonly ApprovalDecisionType $type,
        private readonly ?string $feedback = null,
        private readonly ?array $modifiedArguments = null,
    ) {
    }

    public static function approve(?string $feedback = null): self
    {
        return new self(ApprovalDecisionType::Approved, $feedback);
    }

    public static function reject(?string $reason = null): self
    {
        return new self(ApprovalDecisionType::Rejected, $reason);
    }

    /**
     * @param array<string, mixed> $modifiedArguments
     */
    public static function modify(array $modifiedArguments, ?string $feedback = null): self
    {
        return new self(ApprovalDecisionType::Modified, $feedback, $modifiedArguments);
    }

    public function getType(): ApprovalDecisionType
    {
        return $this->type;
    }

    public function isApproved(): bool
    {
        return ApprovalDecisionType::Approved === $this->type;
    }

    public function isRejected(): bool
    {
        return ApprovalDecisionType::Rejected === $this->type;
    }

    public function isModified(): bool
    {
        return ApprovalDecisionType::Modified === $this->type;
    }

    public function getFeedback(): ?string
    {
        return $this->feedback;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getModifiedArguments(): ?array
    {
        return $this->modifiedArguments;
    }
}
