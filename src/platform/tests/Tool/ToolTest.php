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
use Symfony\AI\Platform\Tool\Tool;

final class ToolTest extends TestCase
{
    public function testReturnsReference()
    {
        $reference = new ExecutionReference('MyClass', 'myMethod');
        $tool = new Tool($reference, 'tool_name', 'tool description');

        $this->assertSame($reference, $tool->getReference());
    }

    public function testReturnsName()
    {
        $reference = new ExecutionReference('MyClass');
        $tool = new Tool($reference, 'tool_name', 'tool description');

        $this->assertSame('tool_name', $tool->getName());
    }

    public function testReturnsDescription()
    {
        $reference = new ExecutionReference('MyClass');
        $tool = new Tool($reference, 'tool_name', 'tool description');

        $this->assertSame('tool description', $tool->getDescription());
    }

    public function testReturnsParametersWhenProvided()
    {
        $reference = new ExecutionReference('MyClass');
        $parameters = [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'A parameter',
                ],
            ],
            'required' => [],
            'additionalProperties' => false,
        ];
        $tool = new Tool($reference, 'tool_name', 'tool description', $parameters);

        $this->assertSame($parameters, $tool->getParameters());
    }

    public function testReturnsNullParametersByDefault()
    {
        $reference = new ExecutionReference('MyClass');
        $tool = new Tool($reference, 'tool_name', 'tool description');

        $this->assertNull($tool->getParameters());
    }
}
