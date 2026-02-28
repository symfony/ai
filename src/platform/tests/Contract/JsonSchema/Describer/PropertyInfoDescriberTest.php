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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PropertyInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Model;
use Symfony\AI\Platform\Contract\JsonSchema\Property;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemName;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

final class PropertyInfoDescriberTest extends TestCase
{
    public function testDescribeDiscriminatorMapObject()
    {
        $describer = new PropertyInfoDescriber();
        $schema = null;

        /** @var list<Property> $actualProperties */
        $actualProperties = iterator_to_array($describer->describeModel(new Model(ListItemName::class, new \ReflectionClass(ListItemName::class)), $schema));

        $this->assertCount(4, $actualProperties);
        $this->assertContainsOnlyInstancesOf(Property::class, $actualProperties);
        $this->assertInstanceOf(\ReflectionProperty::class, $actualProperties[0]->getReflector());
        $this->assertInstanceOf(\ReflectionParameter::class, $actualProperties[1]->getReflector());
        $this->assertInstanceOf(\ReflectionProperty::class, $actualProperties[2]->getReflector());
        $this->assertInstanceOf(\ReflectionParameter::class, $actualProperties[3]->getReflector());
    }

    /**
     * @param array<string, mixed>|null $expectedSchema
     */
    #[TestWith([null, new Property('name', new \ReflectionParameter([UserWithConstructor::class, '__construct'], 'name'))], 'constructor parameter')]
    #[TestWith([['description' => 'The name of the user in lowercase'], new Property('name', new \ReflectionProperty(User::class, 'name'))])]
    public function testDescribeDescription(?array $expectedSchema, Property $property)
    {
        $describer = new PropertyInfoDescriber();
        $schema = null;

        $describer->describeProperty($property, $schema);
        $this->assertSame($expectedSchema, $schema);
    }
}
