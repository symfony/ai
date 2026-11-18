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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\MethodDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ParamDescriptionFixture;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class MethodDescriberTest extends TestCase
{
    /**
     * @param JsonSchema|array<string, mixed> $actual
     * @param JsonSchema|array<string, mixed> $expected
     */
    #[DataProvider('propertyProvider')]
    public function testDescribeProperty(PropertySubject $property, array $actual, array $expected)
    {
        $describer = new MethodDescriber();
        $describer->describeProperty($property, $actual);

        $this->assertSame($expected, $actual);
    }

    public static function propertyProvider(): iterable
    {
        yield 'property' => [
            new PropertySubject('name', new \ReflectionProperty(User::class, 'name')),
            ['type' => 'string'],
            ['type' => 'string'],
        ];

        yield 'constructor promoted property' => [
            new PropertySubject('name', new \ReflectionParameter([UserWithConstructor::class, '__construct'], 'name')),
            ['type' => 'string'],
            [
                'type' => 'string',
                'description' => 'The name of the user in lowercase',
            ],
        ];

        yield 'parameter with a generic type' => [
            new PropertySubject('tags', new \ReflectionParameter([ParamDescriptionFixture::class, '__invoke'], 'tags')),
            ['type' => 'array'],
            [
                'type' => 'array',
                'description' => 'A list of tags',
            ],
        ];

        yield 'parameter with a type containing a space' => [
            new PropertySubject('labels', new \ReflectionParameter([ParamDescriptionFixture::class, '__invoke'], 'labels')),
            ['type' => 'array'],
            [
                'type' => 'array',
                'description' => 'Map of field name to label',
            ],
        ];

        yield 'parameter with an array shape type' => [
            new PropertySubject('person', new \ReflectionParameter([ParamDescriptionFixture::class, '__invoke'], 'person')),
            ['type' => 'array'],
            [
                'type' => 'array',
                'description' => 'The person to greet',
            ],
        ];

        yield 'parameter named in the description of a previous parameter' => [
            new PropertySubject('count', new \ReflectionParameter([ParamDescriptionFixture::class, '__invoke'], 'count')),
            ['type' => 'integer'],
            [
                'type' => 'integer',
                'description' => 'How often to repeat',
            ],
        ];

        yield 'parameter with a multi-line array shape type' => [
            new PropertySubject('address', new \ReflectionParameter([ParamDescriptionFixture::class, '__invoke'], 'address')),
            ['type' => 'array'],
            [
                'type' => 'array',
                'description' => 'The address to ship to',
            ],
        ];

        yield 'parameter with a description wrapped onto a second line' => [
            new PropertySubject('note', new \ReflectionParameter([ParamDescriptionFixture::class, '__invoke'], 'note')),
            ['type' => 'string'],
            [
                'type' => 'string',
                'description' => 'A note that is long enough to wrap onto a second line',
            ],
        ];
    }

    /**
     * @param list<string> $expectedPropertyNames
     */
    #[DataProvider('modelProvider')]
    public function testDescribeModel(ObjectSubject $model, array $expectedPropertyNames)
    {
        $describer = new MethodDescriber();
        $actualProperties = $describer->describeObject($model, $actual);

        $this->assertSame($expectedPropertyNames, array_map(static fn (PropertySubject $property) => $property->getName(), iterator_to_array($actualProperties)));
    }

    public static function modelProvider(): iterable
    {
        yield 'user with constructor' => [
            new ObjectSubject('name', new \ReflectionMethod(UserWithConstructor::class, '__construct')),
            ['id', 'name', 'createdAt', 'isActive', 'age'],
        ];
    }
}
