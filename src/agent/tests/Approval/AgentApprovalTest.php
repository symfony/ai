<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Approval;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Approval\ApprovalDecision;
use Symfony\AI\Agent\Approval\ApprovalManager;
use Symfony\AI\Agent\Approval\ApprovalPendingResult;
use Symfony\AI\Agent\Approval\Attribute\RequiresApproval;
use Symfony\AI\Agent\Approval\Checkpoint\CheckpointSigner;
use Symfony\AI\Agent\Approval\Checkpoint\InMemoryCheckpointStore;
use Symfony\AI\Agent\Approval\Event\ToolApprovalRequestedEvent;
use Symfony\AI\Agent\Approval\Event\ToolApprovalResolvedEvent;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[AsTool(name: 'transfer_money', description: 'Transfers money to an account', method: 'transfer')]
#[RequiresApproval(prompt: 'Transfer ${amount} to account #{toAccount}', roles: ['ROLE_FINANCE_ADMIN'])]
class BankService
{
    /**
     * @var array<array{toAccount: string, amount: float}>
     */
    public array $transfers = [];

    public function transfer(string $toAccount, float $amount): string
    {
        $this->transfers[] = ['toAccount' => $toAccount, 'amount' => $amount];

        return \sprintf('Successfully transferred $%s to %s', $amount, $toAccount);
    }
}

final class AgentApprovalTest extends TestCase
{
    public function testAgentSuspendsExecutionWhenToolRequiresApproval()
    {
        $bankService = new BankService();
        $toolbox = new Toolbox([$bankService]);
        $store = new InMemoryCheckpointStore();
        $signer = new CheckpointSigner('test-secret');
        $approvalManager = new ApprovalManager([], $store, $signer);
        $eventDispatcher = new EventDispatcher();

        $requestedEvents = [];
        $eventDispatcher->addListener(ToolApprovalRequestedEvent::class, static function (ToolApprovalRequestedEvent $e) use (&$requestedEvents) {
            $requestedEvents[] = $e;
        });

        $platform = new InMemoryPlatform(static function ($model, MessageBag $messages) {
            if ($messages->isLastMessageFrom(\Symfony\AI\Platform\Message\Role::User)) {
                return new ToolCallResult([
                    new ToolCall('call_transfer_1', 'transfer_money', ['toAccount' => 'ACC-99', 'amount' => 500.0]),
                ]);
            }

            return new TextResult('Transfer completed!');
        });

        $agent = new Agent(
            platform: $platform,
            model: 'gpt-4o',
            name: 'finance-agent',
            toolbox: $toolbox,
            eventDispatcher: $eventDispatcher,
            approvalManager: $approvalManager,
        );

        $result = $agent->call('Please send $500 to ACC-99')->getResult();

        $this->assertInstanceOf(ApprovalPendingResult::class, $result);
        $this->assertSame('transfer_money', $result->getToolCall()->getName());
        $this->assertSame('Transfer 500 to account ACC-99', $result->getPrompt());
        $this->assertSame(['ROLE_FINANCE_ADMIN'], $result->getRoles());
        $this->assertNotNull($result->getToken());

        $this->assertCount(1, $requestedEvents);
        $this->assertCount(0, $bankService->transfers);
        $this->assertNotNull($store->get($result->getCheckpoint()->getId()));

        // Now resume with Approval
        $resolvedEvents = [];
        $eventDispatcher->addListener(ToolApprovalResolvedEvent::class, static function (ToolApprovalResolvedEvent $e) use (&$resolvedEvents) {
            $resolvedEvents[] = $e;
        });

        $resumeResult = $agent->resume($result->getToken(), ApprovalDecision::approve());

        $this->assertInstanceOf(TextResult::class, $resumeResult);
        $this->assertSame('Transfer completed!', $resumeResult->getContent());
        $this->assertCount(1, $bankService->transfers);
        $this->assertSame('ACC-99', $bankService->transfers[0]['toAccount']);
        $this->assertSame(500.0, $bankService->transfers[0]['amount']);
        $this->assertCount(1, $resolvedEvents);
        $this->assertNull($store->get($result->getCheckpoint()->getId()));
    }

    public function testAgentResumesWithRejection()
    {
        $bankService = new BankService();
        $toolbox = new Toolbox([$bankService]);
        $store = new InMemoryCheckpointStore();
        $signer = new CheckpointSigner('test-secret');
        $approvalManager = new ApprovalManager([], $store, $signer);

        $platform = new InMemoryPlatform(static function ($model, MessageBag $messages) {
            if ($messages->isLastMessageFrom(\Symfony\AI\Platform\Message\Role::User)) {
                return new ToolCallResult([
                    new ToolCall('call_transfer_1', 'transfer_money', ['toAccount' => 'ACC-99', 'amount' => 500.0]),
                ]);
            }

            return new TextResult('I understand the transfer was rejected.');
        });

        $agent = new Agent(
            platform: $platform,
            model: 'gpt-4o',
            toolbox: $toolbox,
            approvalManager: $approvalManager,
        );

        $pendingResult = $agent->call('Send $500')->getResult();
        $this->assertInstanceOf(ApprovalPendingResult::class, $pendingResult);

        $finalResult = $agent->resume($pendingResult->getCheckpoint(), ApprovalDecision::reject('Account not verified'));

        $this->assertInstanceOf(TextResult::class, $finalResult);
        $this->assertSame('I understand the transfer was rejected.', $finalResult->getContent());
        $this->assertCount(0, $bankService->transfers);
    }

    public function testAgentResumesWithModifiedArguments()
    {
        $bankService = new BankService();
        $toolbox = new Toolbox([$bankService]);
        $store = new InMemoryCheckpointStore();
        $signer = new CheckpointSigner('test-secret');
        $approvalManager = new ApprovalManager([], $store, $signer);

        $platform = new InMemoryPlatform(static function ($model, MessageBag $messages) {
            if ($messages->isLastMessageFrom(\Symfony\AI\Platform\Message\Role::User)) {
                return new ToolCallResult([
                    new ToolCall('call_transfer_1', 'transfer_money', ['toAccount' => 'ACC-99', 'amount' => 500.0]),
                ]);
            }

            return new TextResult('Transfer done with modified amount.');
        });

        $agent = new Agent(
            platform: $platform,
            model: 'gpt-4o',
            toolbox: $toolbox,
            approvalManager: $approvalManager,
        );

        $pendingResult = $agent->call('Send $500')->getResult();
        $this->assertInstanceOf(ApprovalPendingResult::class, $pendingResult);

        $finalResult = $agent->resume($pendingResult->getToken(), ApprovalDecision::modify(['toAccount' => 'ACC-99', 'amount' => 100.0]));

        $this->assertInstanceOf(TextResult::class, $finalResult);
        $this->assertCount(1, $bankService->transfers);
        $this->assertSame(100.0, $bankService->transfers[0]['amount']);
    }

    public function testEventListenerImmediateSynchronousDecision()
    {
        $bankService = new BankService();
        $toolbox = new Toolbox([$bankService]);
        $approvalManager = new ApprovalManager();
        $eventDispatcher = new EventDispatcher();

        // Event listener auto-approves synchronously
        $eventDispatcher->addListener(ToolApprovalRequestedEvent::class, static function (ToolApprovalRequestedEvent $e) {
            $e->decide(ApprovalDecision::approve('Auto approved by security rule'));
        });

        $platform = new InMemoryPlatform(static function ($model, MessageBag $messages) {
            if ($messages->isLastMessageFrom(\Symfony\AI\Platform\Message\Role::User)) {
                return new ToolCallResult([
                    new ToolCall('call_transfer_1', 'transfer_money', ['toAccount' => 'ACC-99', 'amount' => 500.0]),
                ]);
            }

            return new TextResult('Finished directly.');
        });

        $agent = new Agent(
            platform: $platform,
            model: 'gpt-4o',
            toolbox: $toolbox,
            eventDispatcher: $eventDispatcher,
            approvalManager: $approvalManager,
        );

        $result = $agent->call('Send $500')->getResult();

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Finished directly.', $result->getContent());
        $this->assertCount(1, $bankService->transfers);
    }
}
