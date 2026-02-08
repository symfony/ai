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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\TypeInfoDescriber;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\TypeInfoFixture;

final class TypeInfoDescriberTest extends TestCase
{
    public function testDescribeAddsNullTypeForNullableScalar()
    {
        $describer = new TypeInfoDescriber();
        $schema = null;

        $describer->describe(new \ReflectionProperty(TypeInfoFixture::class, 'nullableInt'), $schema);

        $this->assertSame(['type' => ['integer', 'null']], $schema);
    }

    public function testDescribeBuildsBackedEnumSchema()
    {
        $describer = new TypeInfoDescriber();
        $schema = null;

        $describer->describe(new \ReflectionProperty(TypeInfoFixture::class, 'backedEnum'), $schema);

        $this->assertSame([
            'type' => 'integer',
            'enum' => [1, 5],
        ], $schema);
    }

    public function testDescribeBuildsNullableBackedEnumSchema()
    {
        $describer = new TypeInfoDescriber();
        $schema = null;

        $describer->describe(new \ReflectionProperty(TypeInfoFixture::class, 'nullableBackedEnum'), $schema);

        $this->assertSame([
            'type' => ['integer', 'null'],
            'enum' => [1, 5],
        ], $schema);
    }

    public function testDescribeThrowsForBuiltinObjectType()
    {
        $describer = new TypeInfoDescriber();
        $schema = null;

        $this->expectException(InvalidArgumentException::class);

        $describer->describe(new \ReflectionProperty(TypeInfoFixture::class, 'payload'), $schema);
    }
}
