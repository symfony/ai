<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Batch;

/**
 * Immutable snapshot of a batch job status returned by the AI provider.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchJob
{
    public function __construct(
        private readonly string $id,
        private readonly BatchStatus $status,
        private readonly int $totalCount = 0,
        private readonly int $processedCount = 0,
        private readonly int $failedCount = 0,
        private readonly ?string $outputFileId = null,
        private readonly ?string $errorFileId = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): BatchStatus
    {
        return $this->status;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Returns the number of requests that have reached a terminal state (processed + failed).
     * Note: for some providers (e.g. OpenAI), cancelled and expired requests may not be included
     * as the API does not expose those counts separately.
     */
    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getOutputFileId(): ?string
    {
        return $this->outputFileId;
    }

    public function getErrorFileId(): ?string
    {
        return $this->errorFileId;
    }

    public function isComplete(): bool
    {
        return BatchStatus::COMPLETED === $this->status;
    }

    public function isFailed(): bool
    {
        return BatchStatus::FAILED === $this->status;
    }

    public function isProcessing(): bool
    {
        return BatchStatus::PROCESSING === $this->status;
    }

    public function isTerminal(): bool
    {
        return \in_array($this->status, [BatchStatus::COMPLETED, BatchStatus::FAILED, BatchStatus::CANCELLED, BatchStatus::EXPIRED], true);
    }
}
