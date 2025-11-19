<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Role;

final class RoleTest extends TestCase
{
    public function testValues()
    {
        $this->assertSame('system', Role::System->value);
        $this->assertSame('assistant', Role::Assistant->value);
        $this->assertSame('user', Role::User->value);
        $this->assertSame('tool', Role::ToolCall->value);
    }

    public function testEquals()
    {
        $this->assertTrue(Role::System->equals(Role::System));
    }

    public function testNotEquals()
    {
        $this->assertTrue(Role::System->notEquals(Role::Assistant));
    }

    public function testNotEqualsOneOf()
    {
        $this->assertTrue(Role::System->notEqualsOneOf([Role::Assistant, Role::User]));
    }

    public function testEqualsOneOf()
    {
        $this->assertTrue(Role::System->equalsOneOf([Role::System, Role::User]));
    }
}
