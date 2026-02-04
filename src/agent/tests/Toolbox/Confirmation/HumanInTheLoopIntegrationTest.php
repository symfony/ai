<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Confirmation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\DeleteFileTool;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ReadFileTool;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\WriteFileTool;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationHandlerInterface;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationResult;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationSubscriber;
use Symfony\AI\Agent\Toolbox\Confirmation\DefaultPolicy;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Integration tests for the human-in-the-loop confirmation system.
 *
 * These tests verify that the Toolbox, ConfirmationSubscriber, and policy
 * components work together correctly to implement tool execution confirmation.
 */
final class HumanInTheLoopIntegrationTest extends TestCase
{
    public function testReadToolIsAutoAllowed()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, true);
        $policy = new DefaultPolicy();

        $toolbox = $this->createToolbox([new ReadFileTool()], $policy, $handler);

        $result = $toolbox->execute(new ToolCall('1', 'read_file', ['path' => '/tmp/test.txt']));

        $this->assertSame('Content of /tmp/test.txt', $result->getResult());
        $this->assertCount(0, $confirmationRequests, 'Read operations should not require confirmation');
    }

    public function testWriteToolRequiresConfirmationAndIsAllowed()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, true);
        $policy = new DefaultPolicy();

        $toolbox = $this->createToolbox([new WriteFileTool()], $policy, $handler);

        $result = $toolbox->execute(new ToolCall('1', 'write_file', ['path' => '/tmp/out.txt', 'content' => 'Hello']));

        $this->assertSame('Written to /tmp/out.txt', $result->getResult());
        $this->assertCount(1, $confirmationRequests);
        $this->assertSame('write_file', $confirmationRequests[0]->getName());
    }

    public function testWriteToolRequiresConfirmationAndIsDenied()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, false);
        $policy = new DefaultPolicy();

        $toolbox = $this->createToolbox([new WriteFileTool()], $policy, $handler);

        $result = $toolbox->execute(new ToolCall('1', 'write_file', ['path' => '/tmp/out.txt', 'content' => 'Hello']));

        $this->assertSame('Tool execution denied by user.', $result->getResult());
        $this->assertCount(1, $confirmationRequests);
    }

    public function testExplicitlyDeniedToolIsBlocked()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, true);
        $policy = new DefaultPolicy();
        $policy->deny('delete_file');

        $toolbox = $this->createToolbox([new DeleteFileTool()], $policy, $handler);

        $result = $toolbox->execute(new ToolCall('1', 'delete_file', ['path' => '/tmp/important.txt']));

        $this->assertSame('Tool execution denied by policy.', $result->getResult());
        $this->assertCount(0, $confirmationRequests, 'Denied tools should not ask for confirmation');
    }

    public function testExplicitlyAllowedToolBypassesConfirmation()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, true);
        $policy = new DefaultPolicy();
        $policy->allow('write_file');

        $toolbox = $this->createToolbox([new WriteFileTool()], $policy, $handler);

        $result = $toolbox->execute(new ToolCall('1', 'write_file', ['path' => '/tmp/out.txt', 'content' => 'Hello']));

        $this->assertSame('Written to /tmp/out.txt', $result->getResult());
        $this->assertCount(0, $confirmationRequests, 'Explicitly allowed tools should not ask for confirmation');
    }

    public function testRememberChoiceAllowsSubsequentCalls()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, true, remember: true);
        $policy = new DefaultPolicy();

        $toolbox = $this->createToolbox([new WriteFileTool()], $policy, $handler);

        // First call - should ask for confirmation
        $toolbox->execute(new ToolCall('1', 'write_file', ['path' => '/tmp/a.txt', 'content' => 'A']));
        $this->assertCount(1, $confirmationRequests);

        // Second call - should NOT ask because choice was remembered
        $toolbox->execute(new ToolCall('2', 'write_file', ['path' => '/tmp/b.txt', 'content' => 'B']));
        $this->assertCount(1, $confirmationRequests, 'Second call should not require confirmation');
    }

    public function testRememberDenialBlocksSubsequentCalls()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, false, remember: true);
        $policy = new DefaultPolicy();

        $toolbox = $this->createToolbox([new WriteFileTool()], $policy, $handler);

        // First call - denied with remember
        $result1 = $toolbox->execute(new ToolCall('1', 'write_file', ['path' => '/tmp/a.txt', 'content' => 'A']));
        $this->assertSame('Tool execution denied by user.', $result1->getResult());
        $this->assertCount(1, $confirmationRequests);

        // Second call - should be denied without asking
        $result2 = $toolbox->execute(new ToolCall('2', 'write_file', ['path' => '/tmp/b.txt', 'content' => 'B']));
        $this->assertSame('Tool execution denied by policy.', $result2->getResult());
        $this->assertCount(1, $confirmationRequests, 'Second call should be denied without asking');
    }

    public function testMixedToolsInSingleSession()
    {
        $confirmationRequests = [];
        $handler = $this->createHandler($confirmationRequests, true);
        $policy = new DefaultPolicy();

        $toolbox = $this->createToolbox([
            new ReadFileTool(),
            new WriteFileTool(),
            new ToolNoParams(),
        ], $policy, $handler);

        // Read operation - auto-allowed
        $result1 = $toolbox->execute(new ToolCall('1', 'read_file', ['path' => '/tmp/test.txt']));
        $this->assertSame('Content of /tmp/test.txt', $result1->getResult());

        // Tool with 'tool_no_params' name - no read pattern, should ask
        $result2 = $toolbox->execute(new ToolCall('2', 'tool_no_params'));
        $this->assertSame('Hello world!', $result2->getResult());

        // Write operation - requires confirmation
        $result3 = $toolbox->execute(new ToolCall('3', 'write_file', ['path' => '/tmp/out.txt', 'content' => 'Data']));
        $this->assertSame('Written to /tmp/out.txt', $result3->getResult());

        // tool_no_params and write_file require confirmation, read_file is auto-allowed
        $this->assertCount(2, $confirmationRequests);
    }

    /**
     * @param list<ToolCall> $confirmationRequests
     */
    private function createHandler(array &$confirmationRequests, bool $confirm, bool $remember = false): ConfirmationHandlerInterface
    {
        return new class($confirmationRequests, $confirm, $remember) implements ConfirmationHandlerInterface {
            /**
             * @param list<ToolCall> $requests
             */
            public function __construct(
                private array &$requests, /** @phpstan-ignore property.onlyWritten */
                private readonly bool $confirm,
                private readonly bool $remember,
            ) {
            }

            public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
            {
                $this->requests[] = $toolCall;

                if ($this->confirm) {
                    return $this->remember ? ConfirmationResult::always() : ConfirmationResult::confirmed();
                }

                return $this->remember ? ConfirmationResult::never() : ConfirmationResult::denied();
            }
        };
    }

    /**
     * @param list<object> $tools
     */
    private function createToolbox(array $tools, DefaultPolicy $policy, ConfirmationHandlerInterface $handler): Toolbox
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ConfirmationSubscriber($policy, $handler));

        return new Toolbox($tools, eventDispatcher: $dispatcher);
    }
}
