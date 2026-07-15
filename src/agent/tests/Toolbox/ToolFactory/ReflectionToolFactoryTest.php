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
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithMetadata;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Tool\Tool;

final class ReflectionToolFactoryTest extends TestCase
{
    public function testMetadataFromAttributeIsCarriedToTheTool()
    {
        $tool = $this->firstTool(ToolWithMetadata::class);

        $this->assertSame(['needs_confirmation' => true, 'category' => 'admin'], $tool->getMetadata());
        $this->assertTrue($tool->getMetadataValue('needs_confirmation'));
        $this->assertSame('admin', $tool->getMetadataValue('category'));
    }

    public function testMetadataDefaultsToEmpty()
    {
        $tool = $this->firstTool(ToolNoParams::class);

        $this->assertSame([], $tool->getMetadata());
        $this->assertNull($tool->getMetadataValue('needs_confirmation'));
        $this->assertTrue($tool->getMetadataValue('needs_confirmation', true));
    }

    private function firstTool(string $className): Tool
    {
        $tools = iterator_to_array((new ReflectionToolFactory())->getTool($className));

        return $tools[0];
    }
}
