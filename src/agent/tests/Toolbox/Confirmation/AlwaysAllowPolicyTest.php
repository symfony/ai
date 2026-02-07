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
use Symfony\AI\Agent\Toolbox\Confirmation\AlwaysAllowPolicy;
use Symfony\AI\Agent\Toolbox\Confirmation\PolicyDecision;
use Symfony\AI\Platform\Result\ToolCall;

final class AlwaysAllowPolicyTest extends TestCase
{
    public function testAlwaysReturnsAllow()
    {
        $policy = new AlwaysAllowPolicy();

        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('1', 'read_file')));
        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('2', 'write_file')));
        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('3', 'delete_everything')));
        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('4', 'execute_command')));
    }
}
