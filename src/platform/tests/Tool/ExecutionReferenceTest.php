<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Tool;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Tool\ExecutionReference;

final class ExecutionReferenceTest extends TestCase
{
    public function testGetClass()
    {
        $reference = new ExecutionReference('MyClass');

        $this->assertSame('MyClass', $reference->getClass());
    }

    public function testGetMethod()
    {
        $reference = new ExecutionReference('MyClass', 'myMethod');

        $this->assertSame('myMethod', $reference->getMethod());
    }

    public function testGetMethodReturnsDefaultInvokeMethod()
    {
        $reference = new ExecutionReference('MyClass');

        $this->assertSame('__invoke', $reference->getMethod());
    }

    public function testConstructorWithClassAndMethod()
    {
        $reference = new ExecutionReference('MyClass', 'execute');

        $this->assertSame('MyClass', $reference->getClass());
        $this->assertSame('execute', $reference->getMethod());
    }

    public function testConstructorWithOnlyClass()
    {
        $reference = new ExecutionReference('AnotherClass');

        $this->assertSame('AnotherClass', $reference->getClass());
        $this->assertSame('__invoke', $reference->getMethod());
    }
}
