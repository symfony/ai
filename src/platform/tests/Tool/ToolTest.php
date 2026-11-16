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
use Symfony\AI\Platform\Tool\ToolEffectHints;

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

    public function testReturnsMetadata()
    {
        $reference = new ExecutionReference('MyClass');
        $tool = new Tool($reference, 'tool_name', 'tool description', null, ['hitl' => true]);

        $this->assertSame(['hitl' => true], $tool->getMetadata());
        $this->assertTrue($tool->getMetadataValue('hitl'));
    }

    public function testReturnsEmptyMetadataByDefault()
    {
        $reference = new ExecutionReference('MyClass');
        $tool = new Tool($reference, 'tool_name', 'tool description');

        $this->assertSame([], $tool->getMetadata());
        $this->assertNull($tool->getMetadataValue('hitl'));
        $this->assertSame('fallback', $tool->getMetadataValue('hitl', 'fallback'));
    }

    public function testEffectHintsDefaultToConservativeValues()
    {
        $tool = new Tool(new ExecutionReference('MyClass'), 'tool', 'description');

        $this->assertFalse($tool->isReadOnly());
        $this->assertTrue($tool->isDestructive());
        $this->assertFalse($tool->isIdempotent());
        $this->assertTrue($tool->isOpenWorld());
    }

    public function testExplicitEffectHints()
    {
        $tool = new Tool(new ExecutionReference('MyClass'), 'tool', 'description',
            effectHints: new ToolEffectHints(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false));

        $this->assertTrue($tool->isReadOnly());
        $this->assertFalse($tool->isDestructive());
        $this->assertTrue($tool->isIdempotent());
        $this->assertFalse($tool->isOpenWorld());
        $this->assertSame(['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false], $tool->getMetadata());
    }

    public function testMissingHintsRemainUnset()
    {
        $tool = new Tool(new ExecutionReference('MyClass'), 'tool', 'description');

        $this->assertNull($tool->getEffectHints()->readOnlyHint);
        $this->assertNull($tool->getEffectHints()->destructiveHint);
        $this->assertNull($tool->getEffectHints()->idempotentHint);
        $this->assertNull($tool->getEffectHints()->openWorldHint);
        $this->assertSame([], $tool->getEffectHints()->toArray());
    }

    public function testReadOnlyToolsCannotBeDestructive()
    {
        $tool = new Tool(new ExecutionReference('MyClass'), 'tool', 'description',
            effectHints: new ToolEffectHints(readOnlyHint: true, destructiveHint: true, idempotentHint: false));

        $this->assertFalse($tool->isDestructive());
        $this->assertTrue($tool->isIdempotent());
        $this->assertTrue($tool->getEffectHints()->destructiveHint);
        $this->assertFalse($tool->getEffectHints()->idempotentHint);
    }

    public function testExplicitHintsOverrideMetadataWithoutDuplicatingState()
    {
        $tool = new Tool(new ExecutionReference('MyClass'), 'tool', 'description',
            metadata: ['readOnlyHint' => false, 'openWorldHint' => false, 'custom' => 'value'],
            effectHints: new ToolEffectHints(readOnlyHint: true));

        $this->assertTrue($tool->isReadOnly());
        $this->assertFalse($tool->isOpenWorld());
        $this->assertTrue($tool->getMetadataValue('readOnlyHint'));
        $this->assertSame('value', $tool->getMetadataValue('custom'));
        $this->assertSame(['readOnlyHint' => true, 'openWorldHint' => false], $tool->getEffectHints()->toArray());
    }
}
