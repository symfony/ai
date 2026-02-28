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
interface WorkflowStateInterface
{
    public function getId(): string;

    public function getCurrentStep(): string;

    public function setCurrentStep(string $step): void;

    public function getContext(): array;

    public function setContext(array $context): void;

    public function mergeContext(array $context): void;

    public function getMetadata(): array;

    public function setMetadata(array $metadata): void;

    public function getCreatedAt(): \DateTimeInterface;

    public function getUpdatedAt(): \DateTimeInterface;

    public function getStatus(): WorkflowStatus;

    public function setStatus(WorkflowStatus $status): void;

    public function getErrors(): array;

    public function addError(WorkflowError $error): void;

    public function clearErrors(): void;

    public function toArray(): array;
}
