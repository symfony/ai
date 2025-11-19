<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallTest extends TestCase
{
    public function testToolCall()
    {
        $toolCall = new ToolCall('id', 'name', ['foo' => 'bar']);
        $this->assertSame('id', $toolCall->getId());
        $this->assertSame('name', $toolCall->getName());
        $this->assertSame(['foo' => 'bar'], $toolCall->getArguments());
    }

    public function testGetId()
    {
        $toolCall = new ToolCall('test-id', 'function-name');
        $this->assertSame('test-id', $toolCall->getId());
    }

    public function testGetName()
    {
        $toolCall = new ToolCall('id', 'test-function');
        $this->assertSame('test-function', $toolCall->getName());
    }

    public function testGetArguments()
    {
        $arguments = ['param1' => 'value1', 'param2' => 42];
        $toolCall = new ToolCall('id', 'name', $arguments);
        $this->assertSame($arguments, $toolCall->getArguments());
    }

    public function testGetArgumentsReturnsEmptyArrayByDefault()
    {
        $toolCall = new ToolCall('id', 'name');
        $this->assertSame([], $toolCall->getArguments());
    }

    public function testToolCallJsonSerialize()
    {
        $toolCall = new ToolCall('id', 'name', ['foo' => 'bar']);
        $this->assertSame([
            'id' => 'id',
            'type' => 'function',
            'function' => [
                'name' => 'name',
                'arguments' => '{"foo":"bar"}',
            ],
        ], $toolCall->jsonSerialize());
    }
}
