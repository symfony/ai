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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Confirmation\DefaultPolicy;
use Symfony\AI\Agent\Toolbox\Confirmation\PolicyDecision;
use Symfony\AI\Platform\Result\ToolCall;

final class DefaultPolicyTest extends TestCase
{
    #[DataProvider('readPatternProvider')]
    public function testReadPatternsAreAllowed(string $toolName)
    {
        $policy = new DefaultPolicy();

        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('1', $toolName)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function readPatternProvider(): iterable
    {
        yield 'read' => ['read_file'];
        yield 'get' => ['get_user'];
        yield 'list' => ['list_files'];
        yield 'search' => ['search_documents'];
        yield 'find' => ['find_matches'];
        yield 'show' => ['show_details'];
        yield 'describe' => ['describe_schema'];
        yield 'camelCase' => ['readUserProfile'];
    }

    public function testSubstringMatchDoesNotFalsePositive()
    {
        $policy = new DefaultPolicy();

        // 'broadcast' contains 'read' as substring but is not a word match
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('1', 'broadcast_message')));
        // 'rewrite' contains no read patterns as whole words
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('2', 'rewrite_data')));
    }

    public function testPatternsAreCaseSensitive()
    {
        $policy = new DefaultPolicy();

        // Default patterns are lowercase, so uppercase tool names don't match
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('1', 'READ_DATA')));
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('2', 'GetUserProfile')));
    }

    public function testUnknownToolAsksUser()
    {
        $policy = new DefaultPolicy();

        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('1', 'write_file')));
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('2', 'delete_record')));
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('3', 'execute_command')));
    }

    public function testExplicitAllowOverridesReadPattern()
    {
        $policy = new DefaultPolicy();
        $policy->allow('dangerous_tool');

        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('1', 'dangerous_tool')));
    }

    public function testExplicitDenyOverridesEverything()
    {
        $policy = new DefaultPolicy();
        $policy->deny('read_file');

        $this->assertSame(PolicyDecision::Deny, $policy->decide(new ToolCall('1', 'read_file')));
    }

    public function testDenyTakesPrecedenceOverAllow()
    {
        $policy = new DefaultPolicy();
        $policy->allow('my_tool');
        $policy->deny('my_tool');

        $this->assertSame(PolicyDecision::Deny, $policy->decide(new ToolCall('1', 'my_tool')));
    }

    public function testRememberAllowDecision()
    {
        $policy = new DefaultPolicy();

        // First call should ask user
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('1', 'custom_tool')));

        // Remember the decision
        $policy->remember('custom_tool', PolicyDecision::Allow);

        // Now it should be allowed
        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('2', 'custom_tool')));
    }

    public function testRememberDenyDecision()
    {
        $policy = new DefaultPolicy();

        // Remember deny decision
        $policy->remember('custom_tool', PolicyDecision::Deny);

        $this->assertSame(PolicyDecision::Deny, $policy->decide(new ToolCall('1', 'custom_tool')));
    }

    public function testCustomReadPatterns()
    {
        $policy = new DefaultPolicy();
        $policy->setReadPatterns(['fetch', 'query']);

        // Default patterns no longer work
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('1', 'read_file')));
        $this->assertSame(PolicyDecision::AskUser, $policy->decide(new ToolCall('2', 'get_user')));

        // Custom patterns work
        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('3', 'fetch_data')));
        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('4', 'query_database')));
    }

    public function testFluentInterface()
    {
        $policy = new DefaultPolicy();

        $result = $policy
            ->allow('tool_a')
            ->deny('tool_b')
            ->setReadPatterns(['custom']);

        $this->assertSame($policy, $result);
    }
}
