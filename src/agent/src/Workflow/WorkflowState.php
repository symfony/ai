<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowState implements WorkflowStateInterface
{
    /** @var WorkflowError[] */
    private array $errors = [];

    public function __construct(
        private readonly string $id,
        private string $currentStep,
        private array $context = [],
        private array $metadata = [],
        private WorkflowStatus $status = WorkflowStatus::PENDING,
        private readonly \DateTimeInterface $createdAt = new \DateTimeImmutable(),
        private \DateTimeInterface $updatedAt = new \DateTimeImmutable(),
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCurrentStep(): string
    {
        return $this->currentStep;
    }

    public function setCurrentStep(string $step): void
    {
        $this->currentStep = $step;
        $this->touch();
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
        $this->touch();
    }

    public function mergeContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
        $this->touch();
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getStatus(): WorkflowStatus
    {
        return $this->status;
    }

    public function setStatus(WorkflowStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(WorkflowError $error): void
    {
        $this->errors[] = $error;
        $this->touch();
    }

    public function clearErrors(): void
    {
        $this->errors = [];
        $this->touch();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'currentStep' => $this->currentStep,
            'context' => $this->context,
            'metadata' => $this->metadata,
            'status' => $this->status->value,
            'errors' => array_map(static fn (WorkflowError $e): array => $e->toArray(), $this->errors),
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::RFC3339),
        ];
    }

    public static function fromArray(array $data): self
    {
        $state = new self(
            $data['id'],
            $data['currentStep'],
            $data['context'] ?? [],
            $data['metadata'] ?? [],
            WorkflowStatus::from($data['status'] ?? 'pending'),
            new \DateTimeImmutable($data['createdAt']),
            new \DateTimeImmutable($data['updatedAt']),
        );

        foreach ($data['errors'] ?? [] as $errorData) {
            $state->addError(new WorkflowError(
                $errorData['message'],
                $errorData['step'],
                $errorData['code'] ?? 0,
                null,
                new \DateTimeImmutable($errorData['occurredAt']),
                $errorData['context'] ?? []
            ));
        }

        return $state;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
