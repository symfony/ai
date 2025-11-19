<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\MetadataFactory;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Fixtures\Tool\ToolMisconfigured;
use Symfony\AI\Fixtures\Tool\ToolMultiple;
use Symfony\AI\Fixtures\Tool\ToolNoAttribute1;
use Symfony\AI\Fixtures\Tool\ToolOptionalParam;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;

final class ChainFactoryTest extends TestCase
{
    private ChainFactory $factory;

    protected function setUp(): void
    {
        $factory1 = (new MemoryToolFactory())
            ->addTool(ToolNoAttribute1::class, 'reference', 'A reference tool')
            ->addTool(ToolOptionalParam::class, 'optional_param', 'Tool with optional param', 'bar');
        $factory2 = new ReflectionToolFactory();

        $this->factory = new ChainFactory([$factory1, $factory2]);
    }

    public function testTestGetMetadataNotExistingClass()
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('The reference "NoClass" is not a valid tool.');

        iterator_to_array($this->factory->getTool('NoClass'));
    }

    public function testTestGetMetadataNotConfiguredClass()
    {
        $this->expectException(ToolConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('Method "foo" not found in tool "%s".', ToolMisconfigured::class));

        iterator_to_array($this->factory->getTool(ToolMisconfigured::class));
    }

    public function testTestGetMetadataWithAttributeSingleHit()
    {
        $metadata = iterator_to_array($this->factory->getTool(ToolRequiredParams::class));

        $this->assertCount(1, $metadata);
    }

    public function testTestGetMetadataOverwrite()
    {
        $metadata = iterator_to_array($this->factory->getTool(ToolOptionalParam::class));

        $this->assertCount(1, $metadata);
        $this->assertSame('optional_param', $metadata[0]->getName());
        $this->assertSame('Tool with optional param', $metadata[0]->getDescription());
        $this->assertSame('bar', $metadata[0]->getReference()->getMethod());
    }

    public function testTestGetMetadataWithAttributeDoubleHit()
    {
        $metadata = iterator_to_array($this->factory->getTool(ToolMultiple::class));

        $this->assertCount(2, $metadata);
    }

    public function testTestGetMetadataWithMemorySingleHit()
    {
        $metadata = iterator_to_array($this->factory->getTool(ToolNoAttribute1::class));

        $this->assertCount(1, $metadata);
    }
}
