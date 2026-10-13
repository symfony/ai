<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\MappedRecipe;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithMappedArguments;
use Symfony\AI\Agent\Toolbox\MapToolArgumentsSchemaFactory;
use Symfony\AI\Platform\Contract\JsonSchema\SchemaFactoryInterface;

final class MapToolArgumentsSchemaFactoryTest extends TestCase
{
    public function testMappedMethodIsDescribedByTheMappedClass()
    {
        $schema = ['type' => 'object', 'properties' => ['custom' => ['type' => 'string']]];
        $inner = $this->createMock(SchemaFactoryInterface::class);
        $inner->expects($this->once())->method('buildProperties')->with(MappedRecipe::class, ['serializer_groups' => ['write']])->willReturn($schema);
        $inner->expects($this->never())->method('buildParameters');

        $factory = new MapToolArgumentsSchemaFactory($inner);

        $this->assertSame($schema, $factory->buildParameters(ToolWithMappedArguments::class, '__invoke', ['serializer_groups' => ['write']]));
    }

    public function testUnmappedMethodIsPassedThrough()
    {
        $schema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]];
        $inner = $this->createMock(SchemaFactoryInterface::class);
        $inner->expects($this->once())->method('buildParameters')->with(ToolRequiredParams::class, 'bar', [])->willReturn($schema);

        $factory = new MapToolArgumentsSchemaFactory($inner);

        $this->assertSame($schema, $factory->buildParameters(ToolRequiredParams::class, 'bar'));
    }
}
