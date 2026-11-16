<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\ToolFactory;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Platform\Tool\ToolEffectHints;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class MemoryToolFactoryTest extends TestCase
{
    public function testExplicitEffectHints()
    {
        $factory = new MemoryToolFactory();
        $factory->addTool(ToolNoParams::class, 'tool', 'description',
            effectHints: new ToolEffectHints(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false));
        $tools = iterator_to_array($factory->getTool(ToolNoParams::class));

        $this->assertTrue($tools[0]->isReadOnly());
        $this->assertFalse($tools[0]->isDestructive());
        $this->assertTrue($tools[0]->isIdempotent());
        $this->assertFalse($tools[0]->isOpenWorld());
        $this->assertSame(['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false], $tools[0]->getMetadata());
    }

    public function testDefaultEffectHints()
    {
        $factory = new MemoryToolFactory();
        $factory->addTool(ToolNoParams::class, 'tool', 'description');
        $tools = iterator_to_array($factory->getTool(ToolNoParams::class));

        $this->assertFalse($tools[0]->isReadOnly());
        $this->assertTrue($tools[0]->isDestructive());
        $this->assertFalse($tools[0]->isIdempotent());
        $this->assertTrue($tools[0]->isOpenWorld());
    }
}
