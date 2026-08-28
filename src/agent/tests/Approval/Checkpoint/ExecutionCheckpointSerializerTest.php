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
use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpoint;
use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpointSerializer;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;

final class ExecutionCheckpointSerializerTest extends TestCase
{
    public function testSerializationRoundTrip()
    {
        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Please transfer $100 to Alice'),
            Message::ofAssistant(new ToolCall('call_1', 'check_balance', ['user' => 'Alice'])),
            Message::ofToolCall(new ToolCall('call_1', 'check_balance', ['user' => 'Alice']), 'Balance is $500'),
        );

        $pendingToolCalls = [
            new ToolCall('call_2', 'transfer_funds', ['to' => 'Alice', 'amount' => 100]),
        ];

        $completedResults = [
            new ToolResult(new ToolCall('call_1', 'check_balance', ['user' => 'Alice']), 'Balance is $500'),
        ];

        $sources = new SourceCollection([
            new Source('bank_api', 'https://api.bank.com', 'account data'),
        ]);

        $checkpoint = new ExecutionCheckpoint(
            id: 'checkpoint-123',
            agentName: 'finance-agent',
            model: 'gpt-4o',
            messages: $messages,
            options: ['temperature' => 0.5],
            pendingToolCalls: $pendingToolCalls,
            completedToolResults: $completedResults,
            iterations: 2,
            sources: $sources,
            createdAt: new \DateTimeImmutable('2026-08-28 10:00:00'),
            expiresAt: new \DateTimeImmutable('2026-08-29 10:00:00'),
        );

        $serializer = new ExecutionCheckpointSerializer();
        $json = $serializer->toJson($checkpoint);
        $restored = $serializer->fromJson($json);

        $this->assertSame('checkpoint-123', $restored->getId());
        $this->assertSame('finance-agent', $restored->getAgentName());
        $this->assertSame('gpt-4o', $restored->getModel());
        $this->assertSame(['temperature' => 0.5], $restored->getOptions());
        $this->assertSame(2, $restored->getIterations());
        $this->assertCount(4, $restored->getMessages());
        $this->assertCount(1, $restored->getPendingToolCalls());
        $this->assertSame('transfer_funds', $restored->getPendingToolCalls()[0]->getName());
        $this->assertSame(['to' => 'Alice', 'amount' => 100], $restored->getPendingToolCalls()[0]->getArguments());
        $this->assertCount(1, $restored->getCompletedToolResults());
        $this->assertNotNull($restored->getSources());
        $this->assertCount(1, $restored->getSources()->all());
        $this->assertSame('bank_api', $restored->getSources()->all()[0]->getName());
        $this->assertFalse($restored->isExpired(new \DateTimeImmutable('2026-08-28 12:00:00')));
        $this->assertTrue($restored->isExpired(new \DateTimeImmutable('2026-08-30 12:00:00')));
    }
}
