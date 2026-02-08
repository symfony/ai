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
