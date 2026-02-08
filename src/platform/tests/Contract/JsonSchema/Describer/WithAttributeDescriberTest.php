<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Describer;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithObjectAccessors;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\WithAttributeDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Property;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ExampleDto;

final class WithAttributeDescriberTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedSchema
     */
    #[TestWith([['enum' => [7, 19]], new Property('taxRate', new \ReflectionParameter([ExampleDto::class, '__construct'], 'taxRate'))], 'parameter')]
    #[TestWith([['const' => 42], new Property('value2', new \ReflectionParameter([ToolWithObjectAccessors::class, 'setValue2'], 0))], 'setter')]
    #[TestWith([['pattern' => '^foo$'], new Property('value3', new \ReflectionParameter([ToolWithObjectAccessors::class, '__construct'], 'value3'))], 'constructor')]
    public function testDescribeProperty(array $expectedSchema, Property $property)
    {
        $describer = new WithAttributeDescriber();
        $schema = null;

        $describer->describeProperty($property, $schema);

        $this->assertSame($expectedSchema, $schema);
    }
}
