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

use Symfony\AI\Agent\Approval\Exception\CheckpointExpiredException;
use Symfony\AI\Agent\Approval\Exception\InvalidCheckpointException;

/**
 * HMAC-SHA256 implementation of CheckpointSignerInterface.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class CheckpointSigner implements CheckpointSignerInterface
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
        private readonly ExecutionCheckpointSerializer $serializer = new ExecutionCheckpointSerializer(),
    ) {
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    public function verify(string $payload, string $signature): bool
    {
        return hash_equals($this->sign($payload), $signature);
    }

    public function encode(ExecutionCheckpoint $checkpoint): string
    {
        $json = $this->serializer->toJson($checkpoint);
        $encodedPayload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $rawSignature = hash_hmac('sha256', $encodedPayload, $this->secret, true);
        $encodedSignature = rtrim(strtr(base64_encode($rawSignature), '+/', '-_'), '=');

        return \sprintf('%s.%s', $encodedPayload, $encodedSignature);
    }

    public function decode(string $token): ExecutionCheckpoint
    {
        $parts = explode('.', $token);
        if (2 !== \count($parts)) {
            throw InvalidCheckpointException::signatureMismatch();
        }

        [$encodedPayload, $encodedSignature] = $parts;

        $rawSignature = hash_hmac('sha256', $encodedPayload, $this->secret, true);
        $expectedSignature = rtrim(strtr(base64_encode($rawSignature), '+/', '-_'), '=');

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw InvalidCheckpointException::signatureMismatch();
        }

        $decodedJson = base64_decode(strtr($encodedPayload, '-_', '+/'), true);
        if (false === $decodedJson) {
            throw InvalidCheckpointException::unreadable('Failed to base64 decode token payload.');
        }

        $checkpoint = $this->serializer->fromJson($decodedJson);

        if ($checkpoint->isExpired()) {
            throw CheckpointExpiredException::forCheckpoint($checkpoint->getId());
        }

        return $checkpoint;
    }
}
