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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\TypeInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Property;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\TypeInfoFixture;

final class TypeInfoDescriberTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedSchema
     */
    #[TestWith([['type' => ['integer', 'null']], TypeInfoFixture::class, 'nullableInt'], 'add null for nullable scalar')]
    #[TestWith([['type' => 'integer', 'enum' => [1, 5]], TypeInfoFixture::class, 'backedEnum'], 'backed enum')]
    #[TestWith([['type' => ['integer', 'null'], 'enum' => [1, 5]], TypeInfoFixture::class, 'nullableBackedEnum'], 'nullable backed enum')]
    public function testDescribeAddsNullTypeForNullableScalar(array $expectedSchema, string $class, string $property)
    {
        $describer = new TypeInfoDescriber();
        $schema = null;

        $describer->describeProperty(new Property($property, new \ReflectionProperty($class, $property)), $schema);

        $this->assertSame($expectedSchema, $schema);
    }

    public function testDescribeThrowsForBuiltinObjectType()
    {
        $describer = new TypeInfoDescriber();
        $schema = null;

        $this->expectException(InvalidArgumentException::class);

        $describer->describeProperty(new Property('payload', new \ReflectionProperty(TypeInfoFixture::class, 'payload')), $schema);
    }
}
