<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactory;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithAccessors;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

final class ResponseFormatFactoryTest extends TestCase
{
    #[TestWith(['User', User::class])]
    #[TestWith(['UserWithConstructor', UserWithConstructor::class])]
    #[TestWith(['UserWithAccessors', UserWithAccessors::class])]
    public function testCreate(string $expectedName, string $class)
    {
        $this->assertSame([
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $expectedName,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => [
                            'type' => 'string',
                            'description' => 'The name of the user in lowercase',
                        ],
                        'createdAt' => [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                        'isActive' => ['type' => 'boolean'],
                        'age' => ['type' => ['integer', 'null']],
                    ],
                    'required' => ['id', 'name', 'createdAt', 'isActive', 'age'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ], (new ResponseFormatFactory())->create($class));
    }

    public function testCreateForAnInstanceDescribesOnlyItsMissingProperties()
    {
        $responseFormat = (new ResponseFormatFactory())->create(City::class, new City(name: 'Berlin'));

        $this->assertSame('City', $responseFormat['json_schema']['name']);
        $this->assertSame(['population', 'country', 'mayor'], array_keys($responseFormat['json_schema']['schema']['properties']));
    }

    public function testCreateThrowsWhenTheInstanceHasNoMissingProperties()
    {
        $city = new City(name: 'Berlin', population: 3500000, country: 'Germany', mayor: 'Kai Wegner');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no missing properties left to describe');

        (new ResponseFormatFactory())->create(City::class, $city);
    }
}
