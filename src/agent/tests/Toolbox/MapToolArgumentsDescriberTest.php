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
use Symfony\AI\Agent\Toolbox\MapToolArgumentsDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ObjectDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class MapToolArgumentsDescriberTest extends TestCase
{
    public function testMappedSubjectPreservesContextAndDelegatesSchema()
    {
        $inner = $this->createMock(ObjectDescriberInterface::class);
        $inner->expects($this->once())->method('describeObject')->willReturnCallback(function (ObjectSubject $subject, ?array &$schema): iterable {
            $this->assertSame(MappedRecipe::class, $subject->getName());
            $this->assertInstanceOf(\ReflectionClass::class, $subject->getReflector());
            $this->assertSame(['serializer_groups' => ['write']], $subject->getContext());
            $schema = ['type' => 'object', 'properties' => ['custom' => ['type' => 'string']]];

            return [];
        });
        $schema = null;
        (new MapToolArgumentsDescriber($inner))->describeObject(new ObjectSubject('tool', new \ReflectionMethod(ToolWithMappedArguments::class, '__invoke'), ['serializer_groups' => ['write']]), $schema);
        $this->assertSame(['type' => 'object', 'properties' => ['custom' => ['type' => 'string']]], $schema);
    }

    public function testUnmappedSubjectsArePassedThrough()
    {
        foreach ([new \ReflectionClass(MappedRecipe::class), new \ReflectionMethod(ToolRequiredParams::class, 'bar')] as $reflector) {
            $subject = new ObjectSubject('original', $reflector);
            $inner = $this->createMock(ObjectDescriberInterface::class);
            $inner->expects($this->once())->method('describeObject')->with($this->identicalTo($subject))->willReturn([]);
            $schema = null;
            (new MapToolArgumentsDescriber($inner))->describeObject($subject, $schema);
        }
    }
}
