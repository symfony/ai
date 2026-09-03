<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Approval\Checkpoint;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Approval\Checkpoint\CheckpointSigner;
use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpoint;
use Symfony\AI\Agent\Approval\Exception\CheckpointExpiredException;
use Symfony\AI\Agent\Approval\Exception\InvalidCheckpointException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class CheckpointSignerTest extends TestCase
{
    public function testEncodeAndDecodeValidToken()
    {
        $signer = new CheckpointSigner('test-secret-key');

        $checkpoint = new ExecutionCheckpoint(
            id: 'checkpoint-abc',
            agentName: 'test-agent',
            model: 'gpt-4o',
            messages: new MessageBag(Message::ofUser('Hello')),
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $token = $signer->encode($checkpoint);
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);

        $decoded = $signer->decode($token);
        $this->assertSame('checkpoint-abc', $decoded->getId());
        $this->assertSame('test-agent', $decoded->getAgentName());
        $this->assertSame('gpt-4o', $decoded->getModel());
    }

    public function testDecodeTamperedTokenThrowsException()
    {
        $signer = new CheckpointSigner('test-secret-key');

        $checkpoint = new ExecutionCheckpoint(
            id: 'checkpoint-abc',
            agentName: 'test-agent',
            model: 'gpt-4o',
            messages: new MessageBag(Message::ofUser('Hello')),
        );

        $token = $signer->encode($checkpoint);
        $tamperedToken = substr_replace($token, 'X', 10, 1);

        $this->expectException(InvalidCheckpointException::class);
        $signer->decode($tamperedToken);
    }

    public function testDecodeWithWrongSecretThrowsException()
    {
        $signer1 = new CheckpointSigner('secret-1');
        $signer2 = new CheckpointSigner('secret-2');

        $checkpoint = new ExecutionCheckpoint(
            id: 'checkpoint-abc',
            agentName: 'test-agent',
            model: 'gpt-4o',
            messages: new MessageBag(Message::ofUser('Hello')),
        );

        $token = $signer1->encode($checkpoint);

        $this->expectException(InvalidCheckpointException::class);
        $signer2->decode($token);
    }

    public function testDecodeExpiredTokenThrowsException()
    {
        $signer = new CheckpointSigner('test-secret-key');

        $checkpoint = new ExecutionCheckpoint(
            id: 'checkpoint-expired',
            agentName: 'test-agent',
            model: 'gpt-4o',
            messages: new MessageBag(Message::ofUser('Hello')),
            expiresAt: new \DateTimeImmutable('-10 minutes'),
        );

        $token = $signer->encode($checkpoint);

        $this->expectException(CheckpointExpiredException::class);
        $signer->decode($token);
    }
}
