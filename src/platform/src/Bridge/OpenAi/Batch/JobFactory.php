<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Batch;

use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Maps an OpenAI Batch API payload to an immutable {@see BatchJob}.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class JobFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): BatchJob
    {
        $jobStatus = JobStatus::tryFrom($data['status'] ?? '');

        if (null === $jobStatus) {
            throw new RuntimeException(\sprintf('Unexpected OpenAI batch status "%s".', $data['status'] ?? ''));
        }

        $status = match ($jobStatus) {
            JobStatus::VALIDATING, JobStatus::IN_PROGRESS, JobStatus::FINALIZING, JobStatus::CANCELLING => BatchStatus::PROCESSING,
            JobStatus::COMPLETED => BatchStatus::COMPLETED,
            JobStatus::FAILED => BatchStatus::FAILED,
            JobStatus::CANCELLED => BatchStatus::CANCELLED,
            JobStatus::EXPIRED => BatchStatus::EXPIRED,
        };

        $counts = $data['request_counts'] ?? [];

        return new BatchJob(
            id: $data['id'],
            status: $status,
            totalCount: $counts['total'] ?? 0,
            processedCount: ($counts['completed'] ?? 0) + ($counts['failed'] ?? 0),
            failedCount: $counts['failed'] ?? 0,
            outputFileId: $data['output_file_id'] ?? null,
            errorFileId: $data['error_file_id'] ?? null,
        );
    }
}
