<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Batch;

use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Maps an Anthropic Message Batches payload to an immutable {@see BatchJob}.
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
        $counts = $data['request_counts'] ?? [];

        $processingStatus = ProcessingStatus::tryFrom($data['processing_status'] ?? '');

        if (null === $processingStatus) {
            throw new RuntimeException(\sprintf('Unexpected Anthropic batch processing status "%s".', $data['processing_status'] ?? ''));
        }

        $status = match ($processingStatus) {
            ProcessingStatus::ENDED => ($counts['canceled'] ?? 0) > 0 ? BatchStatus::CANCELLED : BatchStatus::COMPLETED,
            ProcessingStatus::CANCELING => BatchStatus::PROCESSING,
            ProcessingStatus::IN_PROGRESS => BatchStatus::PROCESSING,
        };

        return new BatchJob(
            id: $data['id'],
            status: $status,
            totalCount: (int) array_sum($counts),
            processedCount: ($counts['succeeded'] ?? 0) + ($counts['errored'] ?? 0) + ($counts['canceled'] ?? 0) + ($counts['expired'] ?? 0),
            failedCount: $counts['errored'] ?? 0,
        );
    }
}
