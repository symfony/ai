<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * Resolves the predictions Replicate hands out, reading their output from `/v1/predictions/{id}`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReplicateJobClient implements JobClientInterface
{
    /**
     * Replicate boots the model on demand, so a cold start dominates a short generation's runtime.
     */
    public const DEFAULT_MAX_DURATION = 300;

    public const DEFAULT_POLL_INTERVAL = 1.0;

    /**
     * Anything else stays {@see JobStateCase::UNKNOWN}, so a new provider state does not abort a job.
     */
    private const STATES = [
        'starting' => JobStateCase::QUEUED,
        'processing' => JobStateCase::RUNNING,
        'succeeded' => JobStateCase::SUCCEEDED,
        'failed' => JobStateCase::FAILED,
        'canceled' => JobStateCase::CANCELED,
    ];

    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function supports(JobHandle $handle): bool
    {
        return \is_string($handle->get('prediction_path'));
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        return self::toStatus($this->query($handle));
    }

    public function getResult(JobHandle $handle): ResultInterface
    {
        $data = $this->query($handle);
        $status = self::toStatus($data);

        if (!$status->is(JobStateCase::SUCCEEDED)) {
            throw new JobFailedException($status, \sprintf('The Replicate prediction "%s" is not ready to be fetched, its status is "%s".', $handle->getId(), $status->getRaw()));
        }

        $output = $data['output'] ?? throw new RuntimeException(\sprintf('The Replicate prediction "%s" does not contain output.', $handle->getId()));

        // Token-by-token models answer with a list of chunks, others with the whole text at once.
        return new TextResult(\is_array($output) ? implode('', $output) : (string) $output);
    }

    /**
     * @return array<string, mixed>
     */
    private function query(JobHandle $handle): array
    {
        $predictionPath = $handle->get('prediction_path');

        if (!\is_string($predictionPath)) {
            throw new RuntimeException(\sprintf('The job handle "%s" does not carry a Replicate prediction path.', $handle->getId()));
        }

        return $this->client->get($predictionPath);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function toStatus(array $data): JobStatus
    {
        $raw = (string) ($data['status'] ?? '');
        $error = $data['error'] ?? null;

        return new JobStatus(self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN, $raw, \is_string($error) && '' !== $error ? $error : null);
    }
}
