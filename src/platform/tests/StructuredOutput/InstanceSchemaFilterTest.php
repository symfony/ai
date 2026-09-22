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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\StructuredOutput\InstanceSchemaFilter;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Itinerary;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Step;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Ticket;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Trip;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithAccessors;

final class InstanceSchemaFilterTest extends TestCase
{
    private InstanceSchemaFilter $filter;
    private Factory $factory;

    protected function setUp(): void
    {
        $this->filter = new InstanceSchemaFilter();
        $this->factory = new Factory();
    }

    public function testKeepsOnlyMissingProperties()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'population' => ['type' => ['integer', 'null']],
                'mayor' => ['type' => ['string', 'null']],
            ],
        ];

        $this->assertSame($expected, $this->filterFor(new City(name: 'Berlin', country: 'Germany')));
    }

    public function testTreatsUninitializedAndNullAsMissing()
    {
        $user = new User();
        $this->assertSame(['id', 'name', 'createdAt', 'isActive', 'age'], array_keys($this->filterFor($user)['properties']));

        $user->id = 1;
        $user->name = 'john';
        $user->isActive = false;
        $this->assertSame(['createdAt', 'age'], array_keys($this->filterFor($user)['properties']));
    }

    public function testReadsPrivatePropertiesBehindAccessors()
    {
        $user = new UserWithAccessors();
        $user->setId(1);
        $user->setIsActive(true);

        $this->assertSame(['name', 'createdAt', 'age'], array_keys($this->filterFor($user)['properties']));
    }

    public function testDescribesEmptyCollectionsWithTheirFullItemSchema()
    {
        // An empty collection is missing and keeps its full item schema
        $schema = $this->filterFor(new MathReasoning([], '', 0.0));

        $this->assertSame(['steps'], array_keys($schema['properties']));
        $this->assertSame(['steps'], $schema['required']);
        $this->assertSame(['explanation', 'output'], array_keys($schema['properties']['steps']['items']['properties']));
    }

    public function testTreatsEmptyCollectionObjectsAsMissing()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ['string', 'null']],
                'stops' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        $this->assertSame(['stops'], array_keys($this->filter->filter($schema, new Itinerary(name: 'Tour'))['properties']));

        $this->expectException(InvalidArgumentException::class);

        $this->filter->filter($schema, new Itinerary(name: 'Tour', stops: new \ArrayObject(['Berlin'])));
    }

    public function testNarrowsNestedObjects()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => ['string', 'null']],
                // Partially filled: only what is still missing on it, and no longer nullable
                'destination' => [
                    'type' => 'object',
                    'properties' => [
                        'population' => ['type' => ['integer', 'null']],
                        'mayor' => ['type' => ['string', 'null']],
                    ],
                ],
                // Not set at all: the full class
                'origin' => [
                    'type' => ['object', 'null'],
                    'properties' => [
                        'name' => ['type' => ['string', 'null']],
                        'population' => ['type' => ['integer', 'null']],
                        'country' => ['type' => ['string', 'null']],
                        'mayor' => ['type' => ['string', 'null']],
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $this->filterFor(new Trip(destination: new City(name: 'Berlin', country: 'Germany'))));
    }

    public function testDropsNestedObjectsWithNothingMissing()
    {
        $trip = new Trip(destination: new City(name: 'Berlin', population: 3500000, country: 'Germany', mayor: 'Kai Wegner'));

        $schema = $this->filterFor($trip);

        $this->assertSame(['title', 'origin'], array_keys($schema['properties']));
    }

    public function testDropsPropertiesThatCannotBeWrittenOntoTheInstance()
    {
        // The readonly code can only be set through the constructor, which populating an existing instance never calls
        $this->assertSame(['seat'], array_keys($this->filterFor(new Ticket())['properties']));
    }

    public function testThrowsWhenNothingIsMissing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The given "%s" instance has no missing properties left to describe.', City::class));

        $this->filterFor(new City(name: 'Berlin', population: 3500000, country: 'Germany', mayor: 'Kai Wegner'));
    }

    public function testThrowsWhenOnlyScalarsAndFilledCollectionsAreGiven()
    {
        $this->expectException(InvalidArgumentException::class);

        // A non-empty collection, an empty string and a zero are all given: nothing is left to describe
        $this->filterFor(new MathReasoning([new Step('a', 'b')], '', 0.0));
    }

    public function testKeepsSchemaWithoutPropertiesAsIs()
    {
        $this->assertSame(['type' => 'object'], $this->filter->filter(['type' => 'object'], new City()));
    }

    /**
     * @return array<string, mixed>
     */
    private function filterFor(object $instance): array
    {
        $schema = $this->factory->buildProperties($instance::class);
        $this->assertNotNull($schema);

        return $this->filter->filter($schema, $instance);
    }
}
