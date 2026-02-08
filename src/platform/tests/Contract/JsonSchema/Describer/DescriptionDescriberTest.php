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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PhpDocDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class DescriptionDescriberTest extends TestCase
{
    /**
     * @param \ReflectionProperty|\ReflectionParameter|\ReflectionClass<User> $reflector
     * @param JsonSchema|array<string, mixed>                                 $actual
     * @param JsonSchema|array<string, mixed>                                 $expected
     */
    #[DataProvider('describeCases')]
    public function testDescribe(\ReflectionProperty|\ReflectionParameter|\ReflectionClass $reflector, array $actual, array $expected)
    {
        $describer = new PhpDocDescriber();
        $describer->describe($reflector, $actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{0: \ReflectionProperty|\ReflectionParameter|\ReflectionClass<User>, 1: array, 2: array}>
     */
    public static function describeCases(): iterable
    {
        yield 'property docblock' => [
            new \ReflectionProperty(User::class, 'name'),
            ['type' => 'string'],
            [
                'type' => 'string',
                'description' => 'The name of the user in lowercase',
            ],
        ];

        yield 'constructor param docblock' => [
            new \ReflectionProperty(UserWithConstructor::class, 'name'),
            ['type' => 'string'],
            [
                'type' => 'string',
                'description' => 'The name of the user in lowercase',
            ],
        ];

        yield 'parameter docblock' => [
            new \ReflectionParameter([UserWithConstructor::class, '__construct'], 'name'),
            ['type' => 'string'],
            [
                'type' => 'string',
                'description' => 'The name of the user in lowercase',
            ],
        ];

        yield 'class reflection' => [
            new \ReflectionClass(User::class),
            ['type' => 'object'],
            ['type' => 'object'],
        ];
    }
}
