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

/**
 * Signs, verifies, encodes and decodes execution checkpoints for tamper-proof transport.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
interface CheckpointSignerInterface
{
    /**
     * Generates a cryptographic signature for the given payload.
     */
    public function sign(string $payload): string;

    /**
     * Verifies that the payload matches the cryptographic signature.
     */
    public function verify(string $payload, string $signature): bool;

    /**
     * Serializes, signs, and encodes an ExecutionCheckpoint into a secure URL-safe token.
     */
    public function encode(ExecutionCheckpoint $checkpoint): string;

    /**
     * Decodes and verifies a secure token back into an ExecutionCheckpoint.
     */
    public function decode(string $token): ExecutionCheckpoint;
}
